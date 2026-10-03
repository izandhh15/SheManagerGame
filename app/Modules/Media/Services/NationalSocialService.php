<?php

namespace App\Modules\Media\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\SocialPost;
use Illuminate\Support\Facades\DB;

/**
 * "Redes de la selección": the manager runs the national team's official
 * social accounts — call-up announcements, venue reveals and ticket
 * pricing. Each announcement spawns fan replies (positive or negative
 * depending on the news).
 *
 * Real handles come from config/national_social_handles.php (verified
 * one by one); anything else falls back to a generated handle.
 */
class NationalSocialService
{
    public const TYPE_CONVOCATORIA = 'convocatoria';
    public const TYPE_SEDE = 'sede';
    public const TYPE_ENTRADAS = 'entradas';

    public const TYPES = [
        self::TYPE_CONVOCATORIA,
        self::TYPE_SEDE,
        self::TYPE_ENTRADAS,
    ];

    /** Ticket tiers for the entradas announcement (€). */
    public const TICKET_TIERS = [
        'popular' => 15,
        'normal' => 30,
        'premium' => 60,
    ];

    public function __construct(
        private readonly SocialMediaService $socialMedia,
    ) {}

    /**
     * @return array{ok: bool, message: string, post_id?: string}
     */
    public function announce(Game $game, string $type, array $extra = []): array
    {
        if (! in_array($type, self::TYPES, true)) {
            return ['ok' => false, 'message' => __('game.national_social_invalid_type')];
        }

        if ($game->team?->type !== 'national') {
            return ['ok' => false, 'message' => __('game.national_social_not_available')];
        }

        return DB::transaction(function () use ($game, $type, $extra) {
            $result = match ($type) {
                self::TYPE_CONVOCATORIA => $this->announceConvocatoria($game),
                self::TYPE_SEDE => $this->announceSede($game),
                self::TYPE_ENTRADAS => $this->announceEntradas($game, $extra['tier'] ?? 'normal'),
            };

            if (($result['ok'] ?? true) === false) {
                return $result;
            }

            return ['ok' => true, 'message' => $result['message'], 'post_id' => $result['post']->id];
        });
    }

    /**
     * Automatic announcement when a match venue is confirmed (federation
     * stadium, neutral, men's ground, or a club accepting the request).
     * Idempotent per match: never announces the same fixture twice.
     */
    public function announceVenueConfirmed(Game $game, GameMatch $match): ?SocialPost
    {
        if ($game->team?->type !== 'national') {
            return null;
        }

        $venue = $match->stadium_name ?? $match->neutral_venue_name;
        if (! $venue) {
            return null;
        }

        $already = $this->venueAnnounced($game, $match);
        if ($already) {
            return null;
        }

        $home = $match->homeTeam?->name ?? $game->team?->name ?? '';
        $away = $match->awayTeam?->name ?? '';
        $es = $this->isEs();
        // B10: the literal "de" is only correct in Spanish — with locale en
        // it rendered espanglish ("Saturday 10 de October").
        $dateFormat = $es ? 'l d \d\e F' : 'l d F';
        $date = $match->scheduled_date
            ? ucfirst($match->scheduled_date->copy()->locale(app()->getLocale())->translatedFormat($dateFormat))
            : '';
        $comp = $match->competition?->name;

        $text = $es
            ? "🏟️ ¡OFICIAL! El {$home} vs {$away}" . ($date ? " del {$date}" : '') . " se jugará en {$venue}." . ($comp ? " ({$comp})" : '') . " ¡Nos vemos en la grada! 🎟️"
            : "🏟️ OFFICIAL! {$home} vs {$away}" . ($date ? " on {$date}" : '') . " will be played at {$venue}." . ($comp ? " ({$comp})" : '') . " See you in the stands! 🎟️";

        $capacity = (int) ($match->neutral_venue_capacity ?? 0);
        $post = $this->officialPost($game, $text, $capacity >= 30000 ? rand(1500, 5000) : rand(400, 1500));
        $post->match_id = $match->id;
        $post->save();

        $this->fanReplies(
            $game,
            $post,
            $es ? $this->sedeTemplatesEs($capacity >= 30000) : $this->sedeTemplatesEn($capacity >= 30000),
            $capacity >= 30000 ? 85 : 55,
        );

        return $post;
    }

    /**
     * Whether the venue of this match was already announced on the
     * national feed, by either the automatic (announceVenueConfirmed) or
     * the manual (announce 'sede') path. Both set match_id (M11).
     */
    public function venueAnnounced(Game $game, GameMatch $match): bool
    {
        return SocialPost::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->where('context', 'national_official')
            ->exists();
    }

    private function announceConvocatoria(Game $game): array
    {
        $playerIds = $game->national_squad_player_ids ?? [];
        if (count($playerIds) === 0) {
            return ['ok' => false, 'message' => __('game.national_social_no_squad')];
        }

        // Star names for the announcement (top 3 by overall).
        $names = DB::table('game_player_templates')
            ->where('season', '2026')
            ->whereIn('player_id', array_slice($playerIds, 0, 23))
            ->orderByDesc('overall_score')
            ->limit(3)
            ->pluck('name')
            ->all();

        $es = $this->isEs();
        $window = $game->national_squad_window?->format('d/m/Y') ?? '';

        $textKey = count($names) > 0
            ? 'game.national_social_convocatoria_text'
            : 'game.national_social_convocatoria_text_plain';

        $post = $this->officialPost(
            $game,
            __($textKey, [
                'stars' => implode(', ', $names),
                'window' => $window,
                'count' => count($playerIds),
                'flag' => $this->nationFlag($game),
            ]),
            rand(800, 4000),
        );

        // A call-up always excites, with the usual debate about who's missing.
        $flag = $this->nationFlag($game);
        $this->fanReplies($game, $post, $es ? $this->convocatoriaTemplatesEs($flag) : $this->convocatoriaTemplatesEn($flag), 80);

        return ['post' => $post, 'message' => __('game.national_social_published')];
    }

    private function announceSede(Game $game): array
    {
        $match = GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->first();

        if (! $match) {
            return ['ok' => false, 'message' => __('game.national_social_no_match')];
        }

        $venue = $match->stadium_name ?? $match->neutral_venue_name;
        if (! $venue) {
            return ['ok' => false, 'message' => __('game.national_social_no_venue')];
        }

        // The automatic announcement (announceVenueConfirmed) and this
        // manual one share idempotency: never announce the same venue
        // twice, whichever path ran first (M11).
        if ($this->venueAnnounced($game, $match)) {
            return ['ok' => false, 'message' => __('game.national_social_already_announced')];
        }

        $capacity = $match->neutral_venue_capacity ?? 0;
        $big = $capacity >= 30000;

        $post = $this->officialPost(
            $game,
            __('game.national_social_sede_text', [
                'venue' => $venue,
                'capacity' => $capacity > 0 ? number_format($capacity, 0, ',', '.') : '—',
            ]),
            $big ? rand(1500, 5000) : rand(400, 1500),
        );
        $post->match_id = $match->id;
        $post->save();

        $es = $this->isEs();
        $this->fanReplies(
            $game,
            $post,
            $es ? $this->sedeTemplatesEs($big) : $this->sedeTemplatesEn($big),
            $big ? 85 : 55,
        );

        return ['post' => $post, 'message' => __('game.national_social_published')];
    }

    private function announceEntradas(Game $game, string $tier): array
    {
        if (! isset(self::TICKET_TIERS[$tier])) {
            $tier = 'normal';
        }

        $price = self::TICKET_TIERS[$tier];
        $game->ticket_price = $price;
        $game->save();

        $cheap = $tier === 'popular';

        $post = $this->officialPost(
            $game,
            __('game.national_social_entradas_text', [
                'price' => $price,
                'tier' => __('game.national_social_tier_' . $tier),
                'flag' => $this->nationFlag($game),
            ]),
            $cheap ? rand(1200, 3500) : rand(300, 1200),
        );

        $es = $this->isEs();
        $this->fanReplies(
            $game,
            $post,
            $es ? $this->entradasTemplatesEs($cheap) : $this->entradasTemplatesEn($cheap),
            $cheap ? 85 : 30,
        );

        return ['post' => $post, 'message' => __('game.national_social_published')];
    }

    // ------------------------------------------------------------------
    // Building blocks
    // ------------------------------------------------------------------

    private function officialPost(Game $game, string $text, int $likes): SocialPost
    {
        return SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $game->team?->name ?? 'Selección',
            'author_handle' => $this->nationalHandle($game),
            'text' => $text,
            'image_url' => $this->federationPoster($game),
            'sentiment' => 0,
            'likes' => $likes,
            'context' => 'national_official',
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
                'context' => 'national_official_reply',
                'parent_post_id' => $post->id,
            ]);
        }
    }

    /**
     * The national team's official handle: the REAL account from
     * config/national_social_handles.php when known (verified one by one),
     * falling back to a generated @seleccion_oficial for unmapped teams.
     */
    public function nationalHandle(Game $game): string
    {
        $mapped = $this->mappedSocial($game);
        if (isset($mapped['x'])) {
            return $mapped['x'];
        }

        return $this->generatedHandle($game);
    }

    public function instagramHandle(Game $game): ?string
    {
        return $this->mappedSocial($game)['instagram'] ?? null;
    }

    /**
     * The nation's flag emoji, derived from the team's ISO country code
     * via regional indicators. Computed per nation: never hardcode the
     * Spanish flag in a template shared by every national team (M10).
     */
    public function nationFlag(Game $game): string
    {
        $code = strtoupper((string) ($game->team?->country ?? ''));
        // England has no regional-indicator flag; the UK flag is the
        // widely supported stand-in.
        if ($code === 'EN') {
            $code = 'GB';
        }
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return '';
        }
        $flag = '';
        foreach (str_split($code) as $letter) {
            $flag .= mb_chr(0x1F1E6 + ord($letter) - ord('A'));
        }

        return $flag;
    }

    /** True when the mapped account is the federation's, not a women's one. */
    public function isFederationAccount(Game $game): bool
    {
        return (bool) ($this->mappedSocial($game)['federation'] ?? false);
    }

    /**
     * @return array{x?: string, instagram?: string, federation?: bool}
     */
    private function mappedSocial(Game $game): array
    {
        // Raw DB name: the `name` accessor translates national teams
        // (Spain → España), but the config is keyed by the raw name.
        $teamName = $game->team?->getRawOriginal('name');
        if ($teamName === null || $teamName === '') {
            return [];
        }

        // Indexed directly (not via dot notation) so team names containing
        // dots don't break the lookup.
        $map = (array) config('national_social_handles', []);

        return (array) ($map[$teamName] ?? []);
    }

    private function generatedHandle(Game $game): string
    {
        $name = $game->team?->name ?? 'seleccion';
        $slug = strtolower(ClubSocialService::ascii($name));
        $slug = preg_replace('/[^a-z0-9]+/', '', $slug);

        if ($slug === '') {
            $slug = 'seleccion';
        }

        return '@' . substr($slug, 0, 18) . '_oficial';
    }

    /**
     * The federation's official "comunicado" poster image URL from
     * config/federation_posters.php (verified one by one). Null when
     * unmapped: views fall back to the generic local poster.
     */
    public function federationPoster(Game $game): ?string
    {
        // Raw DB name: the `name` accessor translates national teams
        // (Spain → España), but the config is keyed by the raw name.
        $teamName = $game->team?->getRawOriginal('name');
        if ($teamName === null || $teamName === '') {
            return null;
        }

        $map = (array) config('federation_posters', []);

        return $map[$teamName] ?? null;
    }

    /**
     * Follower count: real figure when known, otherwise a reputation-based
     * estimate. (No verified counts were available for national teams, so
     * this always estimates for now.)
     */
    public function followers(Game $game): string
    {
        $count = 1_200_000;
        // B11: the `name` accessor translates national teams (Spain → España
        // in es), so compare the RAW db name — same as mappedSocial().
        if (($game->team?->getRawOriginal('name') ?? '') === 'Spain') {
            $count = 850_000;
        }

        return $this->formatFollowers($count);
    }

    private function formatFollowers(int $count): string
    {
        if ($count >= 1_000_000) {
            return number_format($count / 1_000_000, 1, ',', '.') . ' M';
        }

        return number_format((int) ($count / 1000), 0, ',', '.') . ' mil';
    }

    /**
     * The national team's official feed.
     *
     * Only the federation's own posts: journalist previews/recaps live in
     * the Internet press section and must never render here with the
     * official badge (M12).
     *
     * @return \Illuminate\Support\Collection<int, SocialPost>
     */
    public function feed(Game $game, int $limit = 30)
    {
        $posts = SocialPost::where('game_id', $game->id)
            ->where('context', 'national_official')
            ->whereNull('parent_post_id')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $replies = SocialPost::where('game_id', $game->id)
            ->where('context', 'national_official_reply')
            ->whereIn('parent_post_id', $posts->pluck('id'))
            ->orderBy('created_at')
            ->get()
            ->groupBy('parent_post_id');

        $posts->each(fn ($p) => $p->setRelation('replies', $replies->get($p->id, collect())));

        return $posts;
    }

    private function isEs(): bool
    {
        return app()->getLocale() === 'es';
    }

    // ------------------------------------------------------------------
    // Fan reply templates
    // ------------------------------------------------------------------

    /** @return array{list<string>, list<string>} */
    private function convocatoriaTemplatesEs(string $flag): array
    {
        return [
            [
                "¡VAMOOOS! Esta lista ilusiona muchísimo {$flag}",
                'Qué buena pinta tiene esta convocatoria, a por todas',
                'Con este equipo podemos ganar a cualquiera 💪',
                'Me encanta la apuesta, hay presente y futuro',
                'Por fin una lista con sentido común 👏',
            ],
            [
                '¿Y fulanita qué? No entiendo nada',
                'Falta gente ahí, esta lista cojea',
                'Siempre se olvida de las mismas...',
                'Con esos nombres no ganamos ni al parchís',
            ],
        ];
    }

    /** @return array{list<string>, list<string>} */
    private function convocatoriaTemplatesEn(string $flag): array
    {
        return [
            [
                "This squad looks exciting, let's go! {$flag}",
                'Great call-up, a real statement of intent',
                'With this team we can beat anyone 💪',
            ],
            [
                'How is she not in the squad?!',
                'This list is missing key players...',
                'Same names overlooked again...',
            ],
        ];
    }

    /** @return array{list<string>, list<string>} */
    private function sedeTemplatesEs(bool $big): array
    {
        $positive = $big
            ? [
                '¡Un estadio así se merece la selección! 🏟️',
                'Va a ser una fiesta, lleno hasta la bandera',
                'Por fin un campo de verdad para ellas 👏',
            ]
            : [
                'Bonito campo para ver a la selección de cerca',
                'Mejor ambiente íntimo que grada vacía',
            ];
        $negative = $big
            ? [
                'A ver si lo llenan, que luego da pena verlo vacío',
            ]
            : [
                '¿En serio en ese campo? Merecemos más',
                'Un campito... vaya ambición',
                'Con lo que genera esta selección...',
            ];

        return [$positive, $negative];
    }

    /** @return array{list<string>, list<string>} */
    private function sedeTemplatesEn(): array
    {
        return [
            ['What a venue for the national team! 🏟️', 'This is going to be a party'],
            ['That ground? We deserve better...', 'Such a small venue...'],
        ];
    }

    /** @return array{list<string>, list<string>} */
    private function entradasTemplatesEs(bool $cheap): array
    {
        if ($cheap) {
            return [
                [
                    '¡Precios populares! Así sí se llena el campo 🎉',
                    'A 15€ voy con toda la familia, bravo 👏',
                    'El fútbol femenino tiene que ser accesible, bien hecho',
                ],
                [
                    'A ver si con estos precios viene gente de verdad',
                ],
            ];
        }

        return [
            [
                'Vale la pena por ver a la selección',
            ],
            [
                '¿60€ por ver a la selección? Se les va la olla',
                'Con esos precios no va ni la familia...',
                'El fútbol es del pueblo, no de los ricos 😡',
                'Vaya atraco, luego lloran porque no va nadie',
            ],
        ];
    }

    /** @return array{list<string>, list<string>} */
    private function entradasTemplatesEn(): array
    {
        return [
            ['Great prices, the stadium will be packed! 🎉'],
            ['Those prices? Outrageous...', 'Football is for the fans, not the rich 😡'],
        ];
    }
}
