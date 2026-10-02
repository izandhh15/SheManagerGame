<?php

namespace App\Modules\Media\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\TeamReputation;
use Illuminate\Support\Facades\DB;

/**
 * The club's official social media ("Redes del club").
 *
 * The manager runs the club's accounts: official announcements for
 * signings, sales, injury reports and season-ticket campaigns. Each
 * announcement spawns fan replies (positive or negative depending on the
 * news), and exciting signings generate hype that fills the stadium:
 * hype boosts home attendance for the next few matches.
 */
class ClubSocialService
{
    public const TYPE_SIGNING = 'signing';
    public const TYPE_SALE = 'sale';
    public const TYPE_INJURY = 'injury';
    public const TYPE_SEASON_TICKETS = 'season_tickets';

    public const TYPES = [
        self::TYPE_SIGNING,
        self::TYPE_SALE,
        self::TYPE_INJURY,
        self::TYPE_SEASON_TICKETS,
    ];

    /** Hype points consumed from the meter after each home game. */
    public const HYPE_DECAY_PER_HOME_GAME = 25;

    /** Attendance boost at 100 hype: +40%. */
    public const HYPE_ATTENDANCE_DIVISOR = 250;

    public function __construct(
        private readonly SocialMediaService $socialMedia,
    ) {}

    /**
     * Publish an official club announcement.
     *
     * @return array{ok:bool, message:string, post_id?:string}
     */
    public function announce(Game $game, string $type, ?string $playerId = null, array $extra = []): array
    {
        if (! in_array($type, self::TYPES, true)) {
            return ['ok' => false, 'message' => __('game.club_social_invalid_type')];
        }

        if ($game->isTournamentMode()) {
            return ['ok' => false, 'message' => __('game.club_social_not_available')];
        }

        $player = null;
        if (in_array($type, [self::TYPE_SIGNING, self::TYPE_SALE, self::TYPE_INJURY], true)) {
            $player = GamePlayer::where('game_id', $game->id)->find($playerId);
            if (! $player) {
                return ['ok' => false, 'message' => __('game.club_social_no_player')];
            }
            if ($this->alreadyAnnounced($game, $type, $player->name)) {
                return ['ok' => false, 'message' => __('game.club_social_already_announced')];
            }
        }

        if ($type === self::TYPE_SEASON_TICKETS && $this->alreadyAnnouncedTickets($game)) {
            return ['ok' => false, 'message' => __('game.club_social_already_announced')];
        }

        return DB::transaction(function () use ($game, $type, $player, $extra) {
            $result = match ($type) {
                self::TYPE_SIGNING => $this->announceSigning($game, $player),
                self::TYPE_SALE => $this->announceSale($game, $player, $extra['destination'] ?? null),
                self::TYPE_INJURY => $this->announceInjury($game, $player, (int) ($extra['weeks'] ?? 4)),
                self::TYPE_SEASON_TICKETS => $this->announceSeasonTickets($game),
            };

            return ['ok' => true, 'message' => $result['message'], 'post_id' => $result['post']->id];
        });
    }

    /**
     * Strip accents without iconv (//TRANSLIT is not available on Edge).
     */
    public static function ascii(string $text): string
    {
        return strtr($text, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
            'Ñ' => 'N', 'Ç' => 'C',
        ]);
    }

    /**
     * The club's official handle, derived from the team name.
     */
    public function clubHandle(Game $game): string
    {
        $name = $game->team?->name ?? 'club';
        $slug = strtolower(self::ascii($name));
        $slug = preg_replace('/\b(cd|cf|ud|sd|rcd|ad|emf|cff)\b/', '', $slug);
        $slug = preg_replace('/\b(femenino|femenina|femenil|women|ladies)\b/', '', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '', $slug);

        if ($slug === '') {
            $slug = 'club';
        }

        return '@' . substr($slug, 0, 18) . '_oficial';
    }

    /**
     * Fake follower count, scaled by the club's reputation tier.
     */
    public function followers(Game $game): string
    {
        $level = ClubProfile::where('team_id', $game->team_id)->value('reputation_level')
            ?? ClubProfile::REPUTATION_LOCAL;

        $base = match ($level) {
            ClubProfile::REPUTATION_ELITE => 2_400_000,
            ClubProfile::REPUTATION_CONTINENTAL => 890_000,
            ClubProfile::REPUTATION_ESTABLISHED => 320_000,
            ClubProfile::REPUTATION_MODEST => 95_000,
            default => 28_000,
        };

        $count = (int) ($base * (0.9 + (crc32($game->team_id) % 20) / 100));

        if ($count >= 1_000_000) {
            return number_format($count / 1_000_000, 1, ',', '.') . ' M';
        }

        return number_format((int) ($count / 1000), 0, ',', '.') . ' mil';
    }

    /**
     * Current hype meter (0-100).
     */
    public function hype(Game $game): int
    {
        return min(100, max(0, (int) ($game->social_hype ?? 0)));
    }

    /**
     * The club's official feed: announcements with their fan replies nested.
     *
     * @return \Illuminate\Support\Collection<int, SocialPost>
     */
    public function feed(Game $game, int $limit = 30)
    {
        $posts = SocialPost::where('game_id', $game->id)
            ->where('context', 'club_official')
            ->whereNull('parent_post_id')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $replies = SocialPost::where('game_id', $game->id)
            ->where('context', 'club_official_reply')
            ->whereIn('parent_post_id', $posts->pluck('id'))
            ->orderBy('created_at')
            ->get()
            ->groupBy('parent_post_id');

        $posts->each(fn ($p) => $p->setRelation('replies', $replies->get($p->id, collect())));

        return $posts;
    }

    // ------------------------------------------------------------------
    // Announcements
    // ------------------------------------------------------------------

    private function announceSigning(Game $game, GamePlayer $player): array
    {
        $es = $this->isEs();
        $club = $game->team?->name ?? ($es ? 'el club' : 'the club');
        $squadAvg = $this->squadAverage($game);
        $overall = (int) ($player->overall_score ?? 70);

        // Excitement: how much better she is than the squad average.
        $excitement = max(5, min(40, $overall - $squadAvg + 12));
        $this->addHype($game, $excitement);

        $text = $es
            ? "🚨 𝗢𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} es nueva jugadora del {$club}. ¡Bienvenida! 💪 #Fichaje"
            : "🚨 𝗢𝗙𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} is a new {$club} player. Welcome! 💪 #Signing";

        $post = $this->officialPost($game, $text, 500 + $excitement * 120 + rand(0, 400));

        // Big signings excite; small ones leave fans cold.
        $positiveChance = $excitement >= 25 ? 90 : ($excitement >= 15 ? 70 : 45);
        $this->fanReplies($game, $post, $this->signingTemplates($player->name, $es), $positiveChance);

        return [
            'post' => $post,
            'message' => __('game.club_social_published'),
        ];
    }

    private function announceSale(Game $game, GamePlayer $player, ?string $destination): array
    {
        $es = $this->isEs();
        $squadAvg = $this->squadAverage($game);
        $overall = (int) ($player->overall_score ?? 70);
        $isStar = $overall >= $squadAvg + 2;
        $dest = $destination ?: ($es ? 'su nuevo club' : 'her new club');

        if ($isStar) {
            $this->addHype($game, -10);
        }

        $text = $es
            ? "ℹ️ Comunicado oficial: {$player->name} se marcha a {$dest}. Gracias por todo y mucha suerte 🍀"
            : "ℹ️ Official statement: {$player->name} leaves for {$dest}. Thank you and good luck 🍀";

        $post = $this->officialPost($game, $text, rand(200, 900));

        // Selling a star angers the fans; selling a fringe player is accepted.
        $positiveChance = $isStar ? 15 : 55;
        $this->fanReplies($game, $post, $this->saleTemplates($player->name, $isStar, $es), $positiveChance);

        return [
            'post' => $post,
            'message' => __('game.club_social_published'),
        ];
    }

    private function announceInjury(Game $game, GamePlayer $player, int $weeks): array
    {
        $es = $this->isEs();

        $text = $es
            ? "🏥 𝗣𝗔𝗥𝗧𝗘 𝗠É𝗗𝗜𝗖𝗢: {$player->name} estará unas {$weeks} semanas de baja. ¡Mucho ánimo, te esperamos! 💪"
            : "🏥 𝗠𝗘𝗗𝗜𝗖𝗔𝗟 𝗥𝗘𝗣𝗢𝗥𝗧: {$player->name} will be out for around {$weeks} weeks. Get well soon! 💪";

        $post = $this->officialPost($game, $text, rand(300, 1200));

        // Mostly supportive, some worried about the sporting impact.
        $this->fanReplies($game, $post, $this->injuryTemplates($player->name, $es), 70);

        return [
            'post' => $post,
            'message' => __('game.club_social_published'),
        ];
    }

    private function announceSeasonTickets(Game $game): array
    {
        $es = $this->isEs();
        $club = $game->team?->name ?? ($es ? 'el club' : 'the club');
        $stadium = $game->team?->stadium_name ?? ($es ? 'nuestro estadio' : 'our stadium');

        $pricing = app(\App\Modules\Stadium\Services\SeasonTicketPricingService::class)
            ->getCurrent($game);
        $price = $pricing ? (int) round($pricing->total_price_cents / 100) : 0;
        $preset = $pricing?->preset ?? 'standard';

        $expensive = in_array($preset, ['premium', 'vip'], true);

        $text = $es
            ? "🎟️ ¡Ya a la venta los abonos {$game->season}! Desde {$price} € para llenar {$stadium} cada jornada. ¡El {$club} te necesita! 🏟️"
            : "🎟️ Season tickets {$game->season} now on sale! From €{$price} to fill {$stadium} every matchday. {$club} needs you! 🏟️";

        $post = $this->officialPost($game, $text, rand(400, 1500));

        // Pricey season tickets upset the fanbase; affordable ones are celebrated.
        $this->fanReplies(
            $game,
            $post,
            $this->seasonTicketTemplates($price, $expensive, $es),
            $expensive ? 20 : 75,
        );

        return [
            'post' => $post,
            'message' => __('game.club_social_published'),
        ];
    }

    // ------------------------------------------------------------------
    // Building blocks
    // ------------------------------------------------------------------

    private function officialPost(Game $game, string $text, int $likes): SocialPost
    {
        return SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $game->team?->name ?? 'Club',
            'author_handle' => $this->clubHandle($game),
            'text' => $text,
            'sentiment' => 0,
            'likes' => $likes,
            'context' => 'club_official',
        ]);
    }

    /**
     * @param list<string> $positiveTemplates
     * @param list<string> $negativeTemplates
     */
    private function fanReplies(Game $game, SocialPost $post, array $templates, int $positiveChance): void
    {
        [$positiveTemplates, $negativeTemplates] = $templates;
        $used = [];
        $count = rand(4, 8);

        for ($i = 0; $i < $count; $i++) {
            $isPositive = rand(1, 100) <= $positiveChance;
            $pool = $isPositive ? $positiveTemplates : $negativeTemplates;
            $text = $pool[array_rand($pool)];

            [$name, $handle] = $this->socialMedia->randomFan($used);

            SocialPost::create([
                'game_id' => $game->id,
                'author_name' => $name,
                'author_handle' => $handle,
                'text' => $text,
                'sentiment' => $isPositive ? 1 : -1,
                'likes' => $isPositive ? rand(5, 150) : rand(10, 300),
                'context' => 'club_official_reply',
                'parent_post_id' => $post->id,
            ]);
        }
    }

    private function addHype(Game $game, int $points): void
    {
        $game->social_hype = min(100, max(0, (int) ($game->social_hype ?? 0) + $points));
        $game->save();
    }

    private function squadAverage(Game $game): float
    {
        $avg = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->avg('overall_score');

        return (float) ($avg ?? 70);
    }

    private function alreadyAnnounced(Game $game, string $type, string $playerName): bool
    {
        return SocialPost::where('game_id', $game->id)
            ->where('context', 'club_official')
            ->whereNull('parent_post_id')
            ->where('text', 'like', '%' . $playerName . '%')
            ->exists();
    }

    private function alreadyAnnouncedTickets(Game $game): bool
    {
        return SocialPost::where('game_id', $game->id)
            ->where('context', 'club_official')
            ->whereNull('parent_post_id')
            ->where('text', 'like', '%🎟️%')
            ->exists();
    }

    private function isEs(): bool
    {
        return app()->getLocale() === 'es';
    }

    // ------------------------------------------------------------------
    // Fan reply templates: [positive, negative]
    // ------------------------------------------------------------------

    /** @return array{0:list<string>, 1:list<string>} */
    private function signingTemplates(string $name, bool $es): array
    {
        $positive = $es ? [
            "VAMOOOOS, qué fichajazo 🔥",
            "Esta sí que ilusiona. A por todas.",
            "Con {$name} subimos el nivel una barbaridad.",
            "La dirección deportiva por fin hace algo bien 👏",
            "Qué ganas de verla con nuestra camiseta.",
            "Fichaje de los que llenan el campo. Allí estaré.",
            "Bienvenida, {$name}. A romperla 💪",
            "Este es el camino. Ilusión máxima.",
        ] : [
            "What a signing 🔥",
            "This one gets me excited. Let's go.",
            "{$name} takes us up a level.",
            "Welcome, {$name}. Tear it up 💪",
            "Signings like this fill stadiums.",
        ];
        $negative = $es ? [
            "A ver si rinde, que el último 'fichajazo'...",
            "Mucho nombre y luego nada. Ya veremos.",
            "No me dice nada este fichaje, la verdad.",
        ] : [
            "Let's see if she delivers.",
            "Big name, we'll see about the rest.",
        ];

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function saleTemplates(string $name, bool $isStar, bool $es): array
    {
        if ($es) {
            $positive = $isStar
                ? ["Gracias por todo, {$name}. Suerte 🍀"]
                : ["Gracias por todo, {$name}. Suerte 🍀", "Era lo mejor para todas las partes.", "A hacer hueco a la cantera."];
            $negative = $isStar
                ? [
                    "¿Pero cómo vendéis a {$name}? Vaya desastre de directiva.",
                    "Se nos va la mejor y no traen a nadie. Increíble.",
                    "Otro año vendiendo a las buenas. Así no se crece.",
                    "Con {$name} fuera, apaga y vámonos.",
                    "La ambición del club, vendida por cuatro duros.",
                ]
                : ["No la vamos a echar de menos, la verdad.", "Venta lógica, pero que traigan a alguien."];
        } else {
            $positive = ["Thanks for everything, {$name}. Good luck 🍀"];
            $negative = $isStar
                ? ["How do you sell {$name}? Shambles.", "Our best player gone. Unbelievable."]
                : ["Won't miss her, honestly."];
        }

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function injuryTemplates(string $name, bool $es): array
    {
        $positive = $es ? [
            "Mucho ánimo, {$name} 💪 Volverás más fuerte.",
            "Qué mala suerte... justo ahora que estaba en su mejor momento.",
            "El equipo te necesita, recupérate bien.",
            "Ánimo campeona, aquí te esperamos ❤️",
            "Paciencia y buena recuperación.",
        ] : [
            "Get well soon, {$name} 💪",
            "So unlucky... right when she was at her best.",
            "Come back stronger ❤️",
        ];
        $negative = $es ? [
            "Sin {$name} nos cuesta el doble. Temporada complicada.",
            "Otra lesionada. ¿Qué pasa con los servicios médicos?",
            "El parte médico ya es tradición en este club.",
        ] : [
            "Without {$name} we're half the team.",
            "Another injury. What's going on?",
        ];

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function seasonTicketTemplates(int $price, bool $expensive, bool $es): array
    {
        $positive = $es ? [
            "A este precio me abono de cabeza 🎟️",
            "Precios populares, así sí. Nos vemos en la grada.",
            "Ya tengo el mío. ¡A llenar el campo!",
            "El club cuida a su gente. Grande.",
        ] : [
            "Season ticket secured 🎟️",
            "Fair prices. See you in the stands.",
        ];
        $negative = $es ? [
            "¿{$price} € por el abono? Se les ha ido la olla.",
            "Con estos precios el campo se queda medio vacío.",
            "El fútbol femenino tiene que ser accesible, no esto.",
            "Suben los precios y el equipo ni compite. Vaya cara.",
        ] : [
            "€{$price} for a season ticket? Outrageous.",
            "At these prices the stands will be half empty.",
        ];

        return [$positive, $negative];
    }
}
