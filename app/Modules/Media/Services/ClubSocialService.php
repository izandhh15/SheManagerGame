<?php

namespace App\Modules\Media\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\RenewalNegotiation;
use App\Models\SocialPost;
use App\Models\TeamReputation;
use App\Modules\Player\Services\PlayerHistoryService;
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
    public const TYPE_VENUE = 'venue';
    public const TYPE_NEXT_HOME = 'next_home';
    public const TYPE_TICKET_DISCOUNT = 'ticket_discount';
    public const TYPE_TICKET_SALES = 'ticket_sales';
    public const TYPE_FRIENDLY = 'friendly';
    public const TYPE_RENEWAL = 'renewal';

    public const TYPES = [
        self::TYPE_SIGNING,
        self::TYPE_SALE,
        self::TYPE_INJURY,
        self::TYPE_SEASON_TICKETS,
        self::TYPE_VENUE,
        self::TYPE_NEXT_HOME,
        self::TYPE_TICKET_DISCOUNT,
        self::TYPE_TICKET_SALES,
        self::TYPE_FRIENDLY,
        self::TYPE_RENEWAL,
    ];

    /** Hype points consumed from the meter after each home game. */
    public const HYPE_DECAY_PER_HOME_GAME = 25;

    /** Attendance boost at 100 hype: +40%. */
    public const HYPE_ATTENDANCE_DIVISOR = 250;

    public function __construct(
        private readonly SocialMediaService $socialMedia,
        private readonly PlayerHistoryService $playerHistory,
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
        if (in_array($type, [self::TYPE_SIGNING, self::TYPE_SALE, self::TYPE_INJURY, self::TYPE_RENEWAL], true)) {
            // R5: the player must belong to the user's own organization —
            // a forged player_id used to reach rival players here (e.g.
            // "renewing" (extending) their contracts via type=renewal).
            $player = GamePlayer::where('game_id', $game->id)->userOwned($game)->find($playerId);
            if (! $player) {
                return ['ok' => false, 'message' => __('game.club_social_no_player')];
            }
            // Renewal has its own kind-scoped duplicate check (a signed
            // player may later be renewed), so it skips the generic one.
            if ($type !== self::TYPE_RENEWAL && $this->alreadyAnnounced($game, $type, $player->name)) {
                return ['ok' => false, 'message' => __('game.club_social_already_announced')];
            }
        }

        if ($type === self::TYPE_SEASON_TICKETS && $this->alreadyAnnouncedTickets($game)) {
            return ['ok' => false, 'message' => __('game.club_social_already_announced')];
        }

        return DB::transaction(function () use ($game, $type, $player, $extra) {
            $result = match ($type) {
                self::TYPE_SIGNING => $this->announceSigning($game, $player),
                self::TYPE_SALE => $this->announceSale($game, $player),
                self::TYPE_INJURY => $this->announceInjury($game, $player, (int) ($extra['weeks'] ?? 4)),
                self::TYPE_SEASON_TICKETS => $this->announceSeasonTickets($game),
                self::TYPE_VENUE => $this->announceVenue($game, $extra['match_id'] ?? null),
                self::TYPE_NEXT_HOME => $this->announceNextHome($game, $extra['match_id'] ?? null),
                self::TYPE_TICKET_DISCOUNT => $this->announceTicketDiscount($game, $extra['match_id'] ?? null),
                self::TYPE_TICKET_SALES => $this->announceTicketSales($game, $extra['match_id'] ?? null),
                self::TYPE_FRIENDLY => $this->announceFriendly($game, $extra['match_id'] ?? null),
                self::TYPE_RENEWAL => $this->announceRenewal($game, $player),
            };

            // Some announcements bail out (no match, already posted):
            // they return post:null with an explanatory message.
            if (($result['ok'] ?? true) === false || ! ($result['post'] ?? null)) {
                return ['ok' => false, 'message' => $result['message']];
            }

            return ['ok' => true, 'message' => $result['message'], 'post_id' => $result['post']->id];
        });
    }

    /**
     * Automatic announcement when a home match moves to a big stadium
     * (e.g. the men's ground). Idempotent per match.
     */
    public function announceVenueConfirmed(Game $game, GameMatch $match): ?SocialPost
    {
        if ($game->isTournamentMode()) {
            return null;
        }

        $venue = $match->stadium_name ?? $match->neutral_venue_name;
        if (! $venue) {
            return null;
        }

        $already = SocialPost::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->where('context', 'club_official')
            ->where(fn ($q) => $q
                ->where('post_kind', self::TYPE_VENUE)
                ->orWhereNull('post_kind'))
            ->exists();
        if ($already) {
            return null;
        }

        $home = $match->homeTeam?->name ?? $game->team?->name ?? '';
        $away = $match->awayTeam?->name ?? '';
        $lang = $this->clubLang($game);
        $date = $this->formatMatchDate($match->scheduled_date, $lang);

        $text = match ($lang) {
            'va' => "🏟️ OFICIAL! El {$home} vs {$away}" . ($date !== '' ? " del {$date}" : '') . " es jugarà a {$venue}. Ens veiem en la grada! 🎟️",
            'ca' => "🏟️ OFICIAL! El {$home} vs {$away}" . ($date !== '' ? " del {$date}" : '') . " es jugarà a {$venue}. Ens veiem a la grada! 🎟️",
            'gl' => "🏟️ OFICIAL! O {$home} vs {$away}" . ($date !== '' ? " do {$date}" : '') . " xogarase en {$venue}. Vémonos na grada! 🎟️",
            default => $this->isEs()
                ? "🏟️ ¡OFICIAL! El {$home} vs {$away}" . ($date !== '' ? " del {$date}" : '') . " se jugará en {$venue}. ¡Nos vemos en la grada! 🎟️"
                : "🏟️ OFFICIAL! {$home} vs {$away}" . ($date !== '' ? " on {$date}" : '') . " will be played at {$venue}. See you in the stands! 🎟️",
        };
        // B16: rival sin nombre dejaba huecos ("El X vs  del Jueves...").
        $text = preg_replace('/\s+/u', ' ', trim($text));

        $post = $this->officialPost($game, $text, rand(400, 3000), self::TYPE_VENUE);
        $post->match_id = $match->id;
        $post->save();

        return $post;
    }

    private function announceVenue(Game $game, ?string $matchId): array
    {
        $match = $matchId ? GameMatch::where('game_id', $game->id)->find($matchId) : null;
        if (! $match) {
            return ['post' => null, 'message' => __('game.club_social_no_match')];
        }
        // announceVenueConfirmed() returns null both when the venue was
        // already announced and when the match has no venue: report each
        // case with its own message instead of the success text (M7).
        if (! ($match->stadium_name ?? $match->neutral_venue_name)) {
            return ['post' => null, 'message' => __('game.club_social_no_venue')];
        }
        $post = $this->announceVenueConfirmed($game, $match);
        if (! $post) {
            return ['post' => null, 'message' => __('game.club_social_already_announced')];
        }
        return ['post' => $post, 'message' => __('game.club_social_published')];
    }

    /**
     * Pre-written: hype the next home matchday.
     */
    private function announceNextHome(Game $game, ?string $matchId): array
    {
        $match = $matchId
            ? GameMatch::where('game_id', $game->id)->find($matchId)
            : $this->nextHomeMatch($game);

        if (! $match) {
            return ['post' => null, 'message' => __('game.club_social_no_home_match')];
        }
        if ($this->alreadyPostedKind($game, self::TYPE_NEXT_HOME, $match->id)) {
            return ['post' => null, 'message' => __('game.club_social_already_announced')];
        }

        $lang = $this->clubLang($game);
        $es = $this->isEs();
        $club = $game->team?->name ?? '';
        $rival = $match->awayTeam?->name ?? '';
        $date = $this->formatMatchDate($match->scheduled_date, $lang);
        $stadium = $match->stadium_name ?? $game->team?->stadium_name ?? '';

        $text = match ($lang) {
            'va' => "🏟️ PRÒXIMA JORNADA A CASA! El {$club} rep el {$rival}" . ($date !== '' ? " el {$date}" : '') . ($stadium !== '' ? " a {$stadium}" : '') . ". Omplim la grada! 🎟️",
            'ca' => "🏟️ PROPERA JORNADA A CASA! El {$club} rep el {$rival}" . ($date !== '' ? " el {$date}" : '') . ($stadium !== '' ? " a {$stadium}" : '') . ". Omplim la grada! 🎟️",
            'gl' => "🏟️ PRÓXIMA XORNADA NA CASA! O {$club} recibe ao {$rival}" . ($date !== '' ? " o {$date}" : '') . ($stadium !== '' ? " en {$stadium}" : '') . ". Enchamos a grada! 🎟️",
            default => $es
                ? "🏟️ ¡PRÓXIMA JORNADA EN CASA! El {$club} recibe al {$rival}" . ($date !== '' ? " el {$date}" : '') . ($stadium !== '' ? " en {$stadium}" : '') . ". ¡Llenemos la grada! 🎟️"
                : "🏟️ NEXT HOME MATCHDAY! {$club} host {$rival}" . ($date !== '' ? " on {$date}" : '') . ($stadium !== '' ? " at {$stadium}" : '') . ". Let's fill the stands! 🎟️",
        };
        // B16: rival sin nombre dejaba huecos ("recibe al  el Martes...").
        $text = preg_replace('/\s+/u', ' ', trim($text));

        $post = $this->officialPost($game, $text, rand(400, 1500), self::TYPE_NEXT_HOME);
        $post->match_id = $match->id;
        $post->save();

        $this->addHype($game, 5);
        $this->fanReplies($game, $post, $this->nextHomeTemplates($lang, $es), 75);

        return ['post' => $post, 'message' => __('game.club_social_published')];
    }

    /**
     * Pre-written: ticket discount for the next home match.
     */
    private function announceTicketDiscount(Game $game, ?string $matchId): array
    {
        $match = $matchId
            ? GameMatch::where('game_id', $game->id)->find($matchId)
            : $this->nextHomeMatch($game);

        if (! $match) {
            return ['post' => null, 'message' => __('game.club_social_no_home_match')];
        }
        if ($this->alreadyPostedKind($game, self::TYPE_TICKET_DISCOUNT, $match->id)) {
            return ['post' => null, 'message' => __('game.club_social_already_announced')];
        }

        $lang = $this->clubLang($game);
        $es = $this->isEs();
        $club = $game->team?->name ?? '';
        $rival = $match->awayTeam?->name ?? '';
        $date = $this->formatMatchDate($match->scheduled_date, $lang);

        $text = match ($lang) {
            'va' => "🎟️ DESCOMPTE! Les entrades per a l'{$club} vs {$rival}" . ($date !== '' ? " del {$date}" : '') . " tenen un 20% de descompte. No et quedes sense la teua!",
            'ca' => "🎟️ DESCOMPTE! Les entrades per a l'{$club} vs {$rival}" . ($date !== '' ? " del {$date}" : '') . " tenen un 20% de descompte. No et quedis sense la teva!",
            'gl' => "🎟️ DESCONTO! As entradas para o {$club} vs {$rival}" . ($date !== '' ? " do {$date}" : '') . " teñen un 20% de desconto. Non quedes sen a túa!",
            default => $es
                ? "🎟️ ¡DESCUENTO! Las entradas para el {$club} vs {$rival}" . ($date !== '' ? " del {$date}" : '') . " tienen un 20% de descuento. ¡No te quedes sin la tuya!"
                : "🎟️ DISCOUNT! Tickets for {$club} vs {$rival}" . ($date !== '' ? " on {$date}" : '') . " are 20% off. Don't miss out!",
        };

        $post = $this->officialPost($game, $text, rand(500, 1800), self::TYPE_TICKET_DISCOUNT);
        $post->match_id = $match->id;
        $post->save();

        $this->addHype($game, 8);
        $this->fanReplies($game, $post, $this->ticketDiscountTemplates($lang, $es), 80);

        return ['post' => $post, 'message' => __('game.club_social_published')];
    }

    /**
     * Pre-written: tickets on sale for the next home match.
     */
    private function announceTicketSales(Game $game, ?string $matchId): array
    {
        $match = $matchId
            ? GameMatch::where('game_id', $game->id)->find($matchId)
            : $this->nextHomeMatch($game);

        if (! $match) {
            return ['post' => null, 'message' => __('game.club_social_no_home_match')];
        }
        if ($this->alreadyPostedKind($game, self::TYPE_TICKET_SALES, $match->id)) {
            return ['post' => null, 'message' => __('game.club_social_already_announced')];
        }

        $lang = $this->clubLang($game);
        $es = $this->isEs();
        $club = $game->team?->name ?? '';
        $rival = $match->awayTeam?->name ?? '';
        $date = $this->formatMatchDate($match->scheduled_date, $lang);
        $stadium = $match->stadium_name ?? $game->team?->stadium_name ?? '';

        $text = match ($lang) {
            'va' => "🎟️ JA A LA VENDA! Les entrades per a l'{$club} vs {$rival}" . ($date !== '' ? " del {$date}" : '') . ($stadium !== '' ? " a {$stadium}" : '') . ". T'esperem en la grada!",
            'ca' => "🎟️ JA A LA VENDA! Les entrades per a l'{$club} vs {$rival}" . ($date !== '' ? " del {$date}" : '') . ($stadium !== '' ? " a {$stadium}" : '') . ". T'esperem a la grada!",
            'gl' => "🎟️ XA Á VENDA! As entradas para o {$club} vs {$rival}" . ($date !== '' ? " do {$date}" : '') . ($stadium !== '' ? " en {$stadium}" : '') . ". Agardámoste na grada!",
            default => $es
                ? "🎟️ ¡YA A LA VENTA! Las entradas para el {$club} vs {$rival}" . ($date !== '' ? " del {$date}" : '') . ($stadium !== '' ? " en {$stadium}" : '') . ". ¡Te esperamos en la grada!"
                : "🎟️ ON SALE NOW! Tickets for {$club} vs {$rival}" . ($date !== '' ? " on {$date}" : '') . ($stadium !== '' ? " at {$stadium}" : '') . ". See you in the stands!",
        };

        $post = $this->officialPost($game, $text, rand(400, 1500), self::TYPE_TICKET_SALES);
        $post->match_id = $match->id;
        $post->save();

        $this->fanReplies($game, $post, $this->ticketSalesTemplates($lang, $es), 75);

        return ['post' => $post, 'message' => __('game.club_social_published')];
    }

    /**
     * Pre-written: announce a preseason friendly.
     */
    private function announceFriendly(Game $game, ?string $matchId): array
    {
        $match = $matchId
            ? GameMatch::where('game_id', $game->id)->find($matchId)
            : $this->nextFriendlyMatch($game);

        if (! $match) {
            return ['post' => null, 'message' => __('game.club_social_no_friendly')];
        }
        if ($this->alreadyPostedKind($game, self::TYPE_FRIENDLY, $match->id)) {
            return ['post' => null, 'message' => __('game.club_social_already_announced')];
        }

        $lang = $this->clubLang($game);
        $es = $this->isEs();
        $club = $game->team?->name ?? '';
        $isHome = $match->home_team_id === $game->team_id;
        $rival = $isHome ? ($match->awayTeam?->name ?? '') : ($match->homeTeam?->name ?? '');
        $date = $this->formatMatchDate($match->scheduled_date, $lang);

        $text = match ($lang) {
            'va' => "🤝 AMISTÓS DE PRETEMPORADA! El {$club} s'enfrontarà " . ($isHome ? "al {$rival} a casa" : "al {$rival} fora de casa") . ($date !== '' ? " el {$date}" : '') . ". Primeres proves del nou curs!",
            'ca' => "🤝 AMISTÓS DE PRETEMPORADA! El {$club} s'enfrontarà " . ($isHome ? "al {$rival} a casa" : "al {$rival} fora de casa") . ($date !== '' ? " el {$date}" : '') . ". Primeres proves del nou curs!",
            'gl' => "🤝 AMIGÁBEL DE PRETEMPADA! O {$club} enfrontarase " . ($isHome ? "ao {$rival} na casa" : "ao {$rival} fóra") . ($date !== '' ? " o {$date}" : '') . ". Primeiras probas do novo curso!",
            default => $es
                ? "🤝 ¡AMISTOSO DE PRETEMPORADA! El {$club} se enfrentará " . ($isHome ? "al {$rival} en casa" : "al {$rival} fuera de casa") . ($date !== '' ? " el {$date}" : '') . ". ¡Primeras pruebas del nuevo curso!"
                : "🤝 PRESEASON FRIENDLY! {$club} will face " . ($isHome ? "{$rival} at home" : "{$rival} away") . ($date !== '' ? " on {$date}" : '') . ". First tests of the new season!",
        };

        $post = $this->officialPost($game, $text, rand(300, 1200), self::TYPE_FRIENDLY);
        $post->match_id = $match->id;
        $post->save();

        $this->fanReplies($game, $post, $this->friendlyTemplates($lang, $es), 70);

        return ['post' => $post, 'message' => __('game.club_social_published')];
    }

    /**
     * Pre-written: announce a contract renewal.
     *
     * R5: this is a PURE announcement. The contract extension itself must
     * come from the real negotiation flow (NegotiateRenewal ->
     * ContractService::processRenewal(): wage demand, salary cap, ledger).
     * This method used to extend contract_until +2 years for free, bypassing
     * the whole economy.
     */
    private function announceRenewal(Game $game, GamePlayer $player): array
    {
        // The renewal must have been genuinely negotiated first: an
        // accepted RenewalNegotiation for this player in this game.
        $negotiated = RenewalNegotiation::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('status', RenewalNegotiation::STATUS_ACCEPTED)
            ->exists();

        if (! $negotiated) {
            return ['post' => null, 'message' => __('game.club_social_renewal_needs_negotiation')];
        }

        // M23: nombre null → patrón '%%' que casa con todo (falso "ya
        // anunciado"); "_" → '%%_%%' (casa con cualquier texto). Sin nombre
        // no hay nada que cotejar, y los comodines LIKE van escapados.
        $already = $player->name
            ? SocialPost::where('game_id', $game->id)
                ->where('context', 'club_official')
                ->where('post_kind', self::TYPE_RENEWAL)
                ->where('text', 'like', '%' . self::likeEscape($player->name) . '%')
                ->exists()
            : false;
        if ($already) {
            return ['post' => null, 'message' => __('game.club_social_already_announced')];
        }

        // No contract mutation here: processRenewal() already set the new
        // end date when the negotiation was accepted.
        $year = $player->contract_until?->year ?? now()->year;

        $lang = $this->clubLang($game);
        $es = $this->isEs();
        $club = $game->team?->name ?? '';

        $text = match ($lang) {
            'va' => "✍️ RENOVADA! {$player->name} amplia el seu contracte amb el {$club} fins a {$year}. Continuem creixent juntes! 💪",
            'ca' => "✍️ RENOVADA! {$player->name} amplia el seu contracte amb el {$club} fins a {$year}. Continuem creixent juntes! 💪",
            'gl' => "✍️ RENOVADA! {$player->name} amplía o seu contrato co {$club} ata {$year}. Seguimos medrando xuntas! 💪",
            default => $es
                ? "✍️ ¡RENOVADA! {$player->name} amplía su contrato con el {$club} hasta {$year}. ¡Seguimos creciendo juntas! 💪"
                : "✍️ RENEWED! {$player->name} extends her {$club} contract until {$year}. Keep growing together! 💪",
        };

        $post = $this->officialPost($game, $text, rand(400, 1500), self::TYPE_RENEWAL);

        $this->addHype($game, 6);
        $this->fanReplies($game, $post, $this->renewalTemplates($player->name, $lang, $es), 80);

        return ['post' => $post, 'message' => __('game.club_social_published')];
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
     * The club's official handle: the REAL account from
     * config/social_handles.php when known (verified one by one), falling
     * back to the generated @nombre_oficial for unmapped clubs.
     */
    public function clubHandle(Game $game): string
    {
        $mapped = $this->mappedSocial($game);
        if (isset($mapped['handle'])) {
            return $mapped['handle'];
        }

        return $this->generatedHandle($game);
    }

    /**
     * Follower count: the real approximate figure when the club is mapped,
     * otherwise the old reputation-scaled fake number.
     */
    public function followers(Game $game): string
    {
        $mapped = $this->mappedSocial($game);
        if (isset($mapped['followers'])) {
            return $this->formatFollowers((int) $mapped['followers']);
        }

        $level = ClubProfile::where('team_id', $game->team_id)->value('reputation_level')
            ?? ClubProfile::REPUTATION_LOCAL;

        $base = match ($level) {
            ClubProfile::REPUTATION_ELITE => 2_400_000,
            ClubProfile::REPUTATION_CONTINENTAL => 890_000,
            ClubProfile::REPUTATION_ESTABLISHED => 320_000,
            ClubProfile::REPUTATION_MODEST => 95_000,
            default => 28_000,
        };

        // R20c (familia C6): crc32() sin máscara da negativo en PHP 32-bit
        // (~50% de los casos) y el multiplicador caía a 0.71–0.89 en vez de
        // 0.9–1.09 → seguidores sistemáticamente bajos en prod.
        $count = (int) ($base * (0.9 + ((crc32($game->team_id) & 0x7FFFFFFF) % 20) / 100));

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
     * The mapped real account for this team, if any. Indexed directly (not
     * via dot notation) so team names containing dots don't break it.
     *
     * @return array{handle?: string, followers?: int}
     */
    private function mappedSocial(Game $game): array
    {
        $teamName = $game->team?->name;
        if ($teamName === null || $teamName === '') {
            return [];
        }

        $byCountry = (array) config("social_handles.{$game->country}", []);

        return (array) ($byCountry[$teamName] ?? []);
    }

    /**
     * Fallback handle for clubs without a mapped real account,
     * derived from the team name.
     */
    private function generatedHandle(Game $game): string
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
     * Current hype meter (0-100).
     */
    public function hype(Game $game): int
    {
        return min(100, max(0, (int) ($game->social_hype ?? 0)));
    }

    /**
     * Language for the club's official announcements: es (español),
     * ca (català), va (valencià) or gl (galego). Resolved from
     * config/club_languages.php: explicit team override first, then the
     * autonomous community from config/team_regions.php.
     */
    public function clubLang(Game $game): string
    {
        $teamName = $game->team?->name;
        $country = $game->country ?? 'ES';

        if ($teamName !== null && $teamName !== '') {
            // Indexed directly (not via dot notation) so team names
            // containing dots don't break the lookup.
            $byCountry = (array) config("club_languages.teams.{$country}", []);
            if (isset($byCountry[$teamName])) {
                return $byCountry[$teamName];
            }

            $teamRegions = (array) config("team_regions.{$country}", []);
            $region = $teamRegions[$teamName] ?? null;
            if ($region !== null) {
                $regionLangs = (array) config('club_languages.regions', []);
                if (isset($regionLangs[$region])) {
                    return $regionLangs[$region];
                }
            }
        }

        return (string) config('club_languages.default', 'es');
    }

    /**
     * The club's official "comunicado" poster image URL from
     * config/club_posters.php (verified one by one). Null when unmapped:
     * views fall back to the generic local poster.
     */
    public function clubPoster(Game $game): ?string
    {
        $teamName = $game->team?->name;
        if ($teamName === null || $teamName === '') {
            return null;
        }

        $byCountry = (array) config('club_posters.' . ($game->country ?? 'ES'), []);

        return $byCountry[$teamName] ?? null;
    }

    /**
     * Next unplayed home match (any competition), or null.
     */
    public function nextHomeMatch(Game $game): ?GameMatch
    {
        return GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->first();
    }

    /**
     * Next unplayed friendly involving the club, or null.
     */
    public function nextFriendlyMatch(Game $game): ?GameMatch
    {
        return GameMatch::where('game_id', $game->id)
            ->where('competition_id', 'FRIENDLY')
            ->where('played', false)
            ->where(fn ($q) => $q
                ->where('home_team_id', $game->team_id)
                ->orWhere('away_team_id', $game->team_id))
            ->orderBy('scheduled_date')
            ->first();
    }

    /**
     * Idempotency check for match-scoped pre-written announcements:
     * one post of this kind per match.
     */
    private function alreadyPostedKind(Game $game, string $kind, string $matchId): bool
    {
        return SocialPost::where('game_id', $game->id)
            ->where('context', 'club_official')
            ->where('post_kind', $kind)
            ->where('match_id', $matchId)
            ->exists();
    }

    /**
     * Match date formatted in the announcement language.
     */
    private function formatMatchDate($date, string $lang): string
    {
        if (! $date) {
            return '';
        }

        $carbonLocale = match ($lang) {
            'va' => 'ca',
            'ca' => 'ca',
            'gl' => 'gl',
            default => 'es',
        };
        // Catalan/Valencian don't use "de" between day and month.
        $format = match ($lang) {
            'va' => 'l d F',
            'ca' => 'l d F',
            default => 'l d \\d\\e F',
        };

        return ucfirst($date->copy()->locale($carbonLocale)->translatedFormat($format));
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
        $lang = $this->clubLang($game);
        $club = $game->team?->name ?? ($es ? 'el club' : 'the club');
        $squadAvg = $this->squadAverage($game);
        $overall = (int) ($player->overall_score ?? 70);

        // Excitement: how much better she is than the squad average.
        $excitement = max(5, min(40, $overall - $squadAvg + 12));

        // Homecoming: she already played for this club earlier in the game.
        $homecoming = $game->team_id
            && $this->playerHistory->isHomecomingSigning($game, $player, $game->team_id);
        if ($homecoming) {
            $excitement = min(40, $excitement + 10);
        }
        $this->addHype($game, $excitement);

        $text = $homecoming ? match ($lang) {
            'va' => "🚨 𝗢𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} torna a casa! Torna al {$club}. Benvinguda de nou! 🏠💪 #TornaACasa",
            'ca' => "🚨 𝗢𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} torna a casa! Torna al {$club}. Benvinguda de nou! 🏠💪 #TornaACasa",
            'gl' => "🚨 𝗢𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} volve á casa! Regresa ao {$club}. Benvida de novo! 🏠💪 #VoltaACasa",
            default => $es
                ? "🚨 𝗢𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} ¡vuelve a casa! Regresa al {$club}. ¡Bienvenida de nuevo! 🏠💪 #VuelveACasa"
                : "🚨 𝗢𝗙𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} is coming home! She returns to {$club}. Welcome back! 🏠💪 #ComingHome",
        } : match ($lang) {
            'va' => "🚨 𝗢𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} és nova jugadora del {$club}. Benvinguda! 💪 #Fitxatge",
            'ca' => "🚨 𝗢𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} és nova jugadora del {$club}. Benvinguda! 💪 #Fitxatge",
            'gl' => "🚨 𝗢𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} é nova xogadora do {$club}. Benvida! 💪 #Fichaxe",
            default => $es
                ? "🚨 𝗢𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} es nueva jugadora del {$club}. ¡Bienvenida! 💪 #Fichaje"
                : "🚨 𝗢𝗙𝗙𝗜𝗖𝗜𝗔𝗟: {$player->name} is a new {$club} player. Welcome! 💪 #Signing",
        };

        $post = $this->officialPost($game, $text, 500 + $excitement * 120 + rand(0, 400), self::TYPE_SIGNING);

        // Big signings excite; small ones leave fans cold.
        $positiveChance = $excitement >= 25 ? 90 : ($excitement >= 15 ? 70 : 45);
        $this->fanReplies($game, $post, $this->signingTemplates($player->name, $lang, $es), $positiveChance);

        return [
            'post' => $post,
            'message' => __('game.club_social_published'),
        ];
    }

    private function announceSale(Game $game, GamePlayer $player): array
    {
        $es = $this->isEs();
        $lang = $this->clubLang($game);
        $squadAvg = $this->squadAverage($game);
        $overall = (int) ($player->overall_score ?? 70);
        $isStar = $overall >= $squadAvg + 2;
        // Destination resolved from the transfer ledger (no free text).
        $dest = $this->saleDestination($game, $player)
            ?? ($es ? 'su nuevo club' : 'her new club');

        if ($isStar) {
            $this->addHype($game, -10);
        }

        $text = match ($lang) {
            'va' => "ℹ️ Comunicat oficial: {$player->name} se'n va a {$dest}. Gràcies per tot i molta sort 🍀",
            'ca' => "ℹ️ Comunicat oficial: {$player->name} marxa a {$dest}. Gràcies per tot i molta sort 🍀",
            'gl' => "ℹ️ Comunicado oficial: {$player->name} marcha a {$dest}. Grazas por todo e moita sorte 🍀",
            default => $es
                ? "ℹ️ Comunicado oficial: {$player->name} se marcha a {$dest}. Gracias por todo y mucha suerte 🍀"
                : "ℹ️ Official statement: {$player->name} leaves for {$dest}. Thank you and good luck 🍀",
        };

        $post = $this->officialPost($game, $text, rand(200, 900), self::TYPE_SALE);

        // Selling a star angers the fans; selling a fringe player is accepted.
        $positiveChance = $isStar ? 15 : 55;
        $this->fanReplies($game, $post, $this->saleTemplates($player->name, $isStar, $lang, $es), $positiveChance);

        return [
            'post' => $post,
            'message' => __('game.club_social_published'),
        ];
    }

    /**
     * Destination club for a sale, resolved from this season's transfer
     * ledger (the sale composer has no free-text field).
     */
    private function saleDestination(Game $game, GamePlayer $player): ?string
    {
        $transfer = GameTransfer::where('game_id', $game->id)
            ->where('season', (string) $game->season)
            ->where('from_team_id', $game->team_id)
            ->where('game_player_id', $player->id)
            ->with('toTeam')
            ->first();

        return $transfer?->toTeam?->name;
    }

    private function announceInjury(Game $game, GamePlayer $player, int $weeks): array
    {
        $es = $this->isEs();
        $lang = $this->clubLang($game);
        // B17: la capa HTTP valida min:1 pero el servicio acepta 0 y
        // negativos -> "unas 0 semanas de baja". Mínimo 1 semana.
        $weeks = max(1, $weeks);

        $text = match ($lang) {
            'va' => "🏥 𝗣𝗔𝗥𝗧 𝗠È𝗗𝗜𝗖: {$player->name} estarà unes {$weeks} setmanes de baixa. Molts ànims, t'esperem! 💪",
            'ca' => "🏥 𝗣𝗔𝗥𝗧 𝗠È𝗗𝗜𝗖: {$player->name} estarà unes {$weeks} setmanes de baixa. Molts ànims, t'esperem! 💪",
            'gl' => "🏥 𝗣𝗔𝗥𝗧𝗘 𝗠É𝗗𝗜𝗖𝗢: {$player->name} estará unhas {$weeks} semanas de baixa. Moito ánimo, agardámoste! 💪",
            default => $es
                ? "🏥 𝗣𝗔𝗥𝗧𝗘 𝗠É𝗗𝗜𝗖𝗢: {$player->name} estará unas {$weeks} semanas de baja. ¡Mucho ánimo, te esperamos! 💪"
                : "🏥 𝗠𝗘𝗗𝗜𝗖𝗔𝗟 𝗥𝗘𝗣𝗢𝗥𝗧: {$player->name} will be out for around {$weeks} weeks. Get well soon! 💪",
        };

        $post = $this->officialPost($game, $text, rand(300, 1200), self::TYPE_INJURY, $player);

        // Mostly supportive, some worried about the sporting impact.
        $this->fanReplies($game, $post, $this->injuryTemplates($player->name, $lang, $es), 70);

        return [
            'post' => $post,
            'message' => __('game.club_social_published'),
        ];
    }

    private function announceSeasonTickets(Game $game): array
    {
        $es = $this->isEs();
        $lang = $this->clubLang($game);
        $club = $game->team?->name ?? ($es ? 'el club' : 'the club');
        $stadium = $game->team?->stadium_name ?? ($es ? 'nuestro estadio' : 'our stadium');

        $pricing = app(\App\Modules\Stadium\Services\SeasonTicketPricingService::class)
            ->getCurrent($game);
        // "Desde X €": the cheapest area's price. (There is no
        // total_price_cents column — the per-area prices live in areas[].)
        $areas = $pricing?->areas ?? [];
        // B18: sin SeasonTicketPricing no hay precio que mostrar: no
        // fabricar un "Desde 0 €", publicar el anuncio sin la mención.
        $hasPricing = count($areas) > 0;
        $minCents = $hasPricing ? min(array_column($areas, 'price_cents')) : 0;
        $price = (int) round($minCents / 100);
        $preset = $pricing?->pricing_preset ?? 'standard';

        $expensive = in_array($preset, ['premium', 'vip'], true);

        $priceBit = match ($lang) {
            'va' => $hasPricing
                ? "Des de {$price} € per a omplir {$stadium} cada jornada. "
                : "A omplir {$stadium} cada jornada. ",
            'ca' => $hasPricing
                ? "Des de {$price} € per omplir {$stadium} cada jornada. "
                : "A omplir {$stadium} cada jornada. ",
            'gl' => $hasPricing
                ? "Desde {$price} € para encher {$stadium} cada xornada. "
                : "A encher {$stadium} cada xornada. ",
            default => $es
                ? ($hasPricing
                    ? "Desde {$price} € para llenar {$stadium} cada jornada. "
                    : "A llenar {$stadium} cada jornada. ")
                : ($hasPricing
                    ? "From €{$price} to fill {$stadium} every matchday. "
                    : "To fill {$stadium} every matchday. "),
        };

        $text = match ($lang) {
            'va' => "🎟️ Ja a la venda els abonaments {$game->season}! {$priceBit}El {$club} et necessita! 🏟️",
            'ca' => "🎟️ Ja a la venda els abonaments {$game->season}! {$priceBit}El {$club} et necessita! 🏟️",
            'gl' => "🎟️ Xa á venda os abonos {$game->season}! {$priceBit}O {$club} necesítate! 🏟️",
            default => $es
                ? "🎟️ ¡Ya a la venta los abonos {$game->season}! {$priceBit}¡El {$club} te necesita! 🏟️"
                : "🎟️ Season tickets {$game->season} now on sale! {$priceBit}{$club} needs you! 🏟️",
        };

        $post = $this->officialPost($game, $text, rand(400, 1500), self::TYPE_SEASON_TICKETS);

        // Pricey season tickets upset the fanbase; affordable ones are celebrated.
        $this->fanReplies(
            $game,
            $post,
            $this->seasonTicketTemplates($price, $expensive, $lang, $es, $hasPricing),
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

    private function officialPost(Game $game, string $text, int $likes, ?string $kind = null, ?GamePlayer $player = null): SocialPost
    {
        return SocialPost::create([
            'game_id' => $game->id,
            // M20: referencia exacta a la jugadora del comunicado (el parte
            // médico se comprueba por id, no por LIKE sobre el nombre).
            'game_player_id' => $player?->id,
            'author_name' => $game->team?->name ?? 'Club',
            'author_handle' => $this->clubHandle($game),
            'text' => $text,
            'image_url' => $this->clubPoster($game),
            'post_kind' => $kind,
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

    /**
     * M23: escapa los comodines de LIKE (\, %, _) en los nombres antes de
     * buscar duplicados. Sin escapar, una jugadora llamada "_" generaba el
     * patrón %%_%% y bloqueaba como duplicado cualquier anuncio.
     */
    private static function likeEscape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function alreadyAnnounced(Game $game, string $type, ?string $playerName): bool
    {
        // A12: game_players.name is nullable. With no name there is nothing
        // to match on, so skip the duplicate check instead of throwing a
        // TypeError (or matching every post with a LIKE '%%' pattern).
        if (! $playerName) {
            return false;
        }

        // A10: the duplicate check must be scoped to the announcement type:
        // announcing a signing must not block the injury report or the sale
        // announcement for the same player.
        return SocialPost::where('game_id', $game->id)
            ->where('context', 'club_official')
            ->whereNull('parent_post_id')
            ->where('post_kind', $type)
            ->where('text', 'like', '%' . self::likeEscape($playerName) . '%')
            ->exists();
    }

    private function alreadyAnnouncedTickets(Game $game): bool
    {
        // A9: only season-ticket posts block the campaign. Other
        // announcements (ticket sales, discounts, venue confirmations) also
        // use the 🎟️ emoji, so filtering by text alone blocks the campaign
        // forever after any of them is published.
        return SocialPost::where('game_id', $game->id)
            ->where('context', 'club_official')
            ->whereNull('parent_post_id')
            ->where('post_kind', self::TYPE_SEASON_TICKETS)
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
    private function signingTemplates(?string $name, string $lang, bool $es): array
    {
        // B9: respuestas de fans en el idioma del club (no siempre español).
        $positive = match ($lang) {
            'va' => [
                "VAMOOOOS, quin fitxatge 🔥",
                "Esta sí que il·lusiona. A per totes.",
                "Amb {$name} pugem el nivell una barbaritat.",
                "La direcció per fi fa alguna cosa bé 👏",
                "Quines ganes de vore-la amb la nostra samarreta.",
                "Fitxatge dels que omplin el camp. Allí estaré.",
                "Benvinguda, {$name}. A rebentar-ho 💪",
                "Este és el camí. Il·lusió total.",
            ],
            'ca' => [
                "VAMOOOOS, quin fitxatge 🔥",
                "Aquesta sí que il·lusiona. A per totes.",
                "Amb {$name} pugem el nivell una barbaritat.",
                "La direcció per fi fa alguna cosa bé 👏",
                "Quines ganes de veure-la amb la nostra samarreta.",
                "Fitxatge dels que omplen el camp. Allà estaré.",
                "Benvinguda, {$name}. A petar-ho 💪",
                "Aquest és el camí. Il·lusió total.",
            ],
            'gl' => [
                "VAMOOOOS, que fichaxe 🔥",
                "Esta si que fai ilusión. A por todas.",
                "Con {$name} subimos o nivel unha barbaridade.",
                "A dirección por fin fai algo ben 👏",
                "Que ganas de vela coa nosa camisola.",
                "Fichaxe dos que enchen o campo. Alí estarei.",
                "Benvida, {$name}. A rompela 💪",
                "Este é o camiño. Ilusión total.",
            ],
            default => $es ? [
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
            ],
        };
        $negative = match ($lang) {
            'va' => [
                "A vore si rendeix, que l'últim 'fitxatge'...",
                "Molt de nom i després res. Ja vorem.",
                "No em diu res este fitxatge, la veritat.",
            ],
            'ca' => [
                "A veure si rendeix, que l'últim 'fitxatge'...",
                "Molt de nom i després res. Ja veurem.",
                "No em diu res aquest fitxatge, la veritat.",
            ],
            'gl' => [
                "A ver se rende, que a última 'fichaxe'...",
                "Moito nome e logo nada. Xa se verá.",
                "Non me di nada esta fichaxe, a verdade.",
            ],
            default => $es ? [
                "A ver si rinde, que el último 'fichajazo'...",
                "Mucho nombre y luego nada. Ya veremos.",
                "No me dice nada este fichaje, la verdad.",
            ] : [
                "Let's see if she delivers.",
                "Big name, we'll see about the rest.",
            ],
        };

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function saleTemplates(?string $name, bool $isStar, string $lang, bool $es): array
    {
        // B9: respuestas de fans en el idioma del club.
        $positive = match ($lang) {
            'va' => $isStar
                ? ["Gràcies per tot, {$name}. Sort 🍀"]
                : ["Gràcies per tot, {$name}. Sort 🍀", "Era el millor per a totes les parts.", "A fer lloc a la pedrera."],
            'ca' => $isStar
                ? ["Gràcies per tot, {$name}. Sort 🍀"]
                : ["Gràcies per tot, {$name}. Sort 🍀", "Era el millor per a totes les parts.", "A fer lloc al planter."],
            'gl' => $isStar
                ? ["Grazas por todo, {$name}. Sorte 🍀"]
                : ["Grazas por todo, {$name}. Sorte 🍀", "Era o mellor para todas as partes.", "A facer oco á canteira."],
            default => $es
                ? ($isStar
                    ? ["Gracias por todo, {$name}. Suerte 🍀"]
                    : ["Gracias por todo, {$name}. Suerte 🍀", "Era lo mejor para todas las partes.", "A hacer hueco a la cantera."])
                : ["Thanks for everything, {$name}. Good luck 🍀"],
        };
        $negative = match ($lang) {
            'va' => $isStar
                ? [
                    "Però com veneu a {$name}? Quin desastre de directiva.",
                    "Se'ns en va la millor i no porten a ningú. Increïble.",
                    "Un altre any venent a les bones. Així no es creix.",
                    "Amb {$name} fora, apaga i anem-nos-en.",
                    "L'ambició del club, venuda per quatre duros.",
                ]
                : ["No la trobarem a faltar, la veritat.", "Venda lògica, però que porten a algú."],
            'ca' => $isStar
                ? [
                    "Però com veneu la {$name}? Quin desastre de directiva.",
                    "Se'ns en va la millor i no porten ningú. Increïble.",
                    "Un altre any venent les bones. Així no es creix.",
                    "Amb la {$name} fora, apaga i anem-nos-en.",
                    "L'ambició del club, venuda per quatre duros.",
                ]
                : ["No la trobarem a faltar, la veritat.", "Venda lògica, però que portin algú."],
            'gl' => $isStar
                ? [
                    "Pero como vendedes a {$name}? Que desastre de directiva.",
                    "Vaisenos a mellor e non traen a ninguén. Incrible.",
                    "Outro ano vendendo ás boas. Así non se medra.",
                    "Con {$name} fóra, apaga e vámonos.",
                    "A ambición do club, vendida por catro cartos.",
                ]
                : ["Non a imos botar de menos, a verdade.", "Venda lóxica, pero que traian a alguén."],
            default => $es
                ? ($isStar
                    ? [
                        "¿Pero cómo vendéis a {$name}? Vaya desastre de directiva.",
                        "Se nos va la mejor y no traen a nadie. Increíble.",
                        "Otro año vendiendo a las buenas. Así no se crece.",
                        "Con {$name} fuera, apaga y vámonos.",
                        "La ambición del club, vendida por cuatro duros.",
                    ]
                    : ["No la vamos a echar de menos, la verdad.", "Venta lógica, pero que traigan a alguien."])
                : ($isStar
                    ? ["How do you sell {$name}? Shambles.", "Our best player gone. Unbelievable."]
                    : ["Won't miss her, honestly."]),
        };

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function injuryTemplates(?string $name, string $lang, bool $es): array
    {
        // B9: respuestas de fans en el idioma del club.
        $positive = match ($lang) {
            'va' => [
                "Molts ànims, {$name} 💪 Tornaràs més forta.",
                "Quina mala sort... just ara que estava en el seu millor moment.",
                "L'equip et necessita, recupera't bé.",
                "Ànims campiona, ací t'esperem ❤️",
                "Paciència i bona recuperació.",
            ],
            'ca' => [
                "Molts ànims, {$name} 💪 Tornaràs més forta.",
                "Quina mala sort... just ara que estava en el seu millor moment.",
                "L'equip et necessita, recupera't bé.",
                "Ànims campiona, aquí t'esperem ❤️",
                "Paciència i bona recuperació.",
            ],
            'gl' => [
                "Moito ánimo, {$name} 💪 Volverás máis forte.",
                "Que mala sorte... xusto agora que estaba no seu mellor momento.",
                "O equipo necesítate, recupérate ben.",
                "Ánimo campioa, aquí agardámoste ❤️",
                "Paciencia e boa recuperación.",
            ],
            default => $es ? [
                "Mucho ánimo, {$name} 💪 Volverás más fuerte.",
                "Qué mala suerte... justo ahora que estaba en su mejor momento.",
                "El equipo te necesita, recupérate bien.",
                "Ánimo campeona, aquí te esperamos ❤️",
                "Paciencia y buena recuperación.",
            ] : [
                "Get well soon, {$name} 💪",
                "So unlucky... right when she was at her best.",
                "Come back stronger ❤️",
            ],
        };
        $negative = match ($lang) {
            'va' => [
                "Sense {$name} ens costa el doble. Temporada complicada.",
                "Una altra lesionada. Què passa amb els serveis mèdics?",
                "El part mèdic ja és tradició en este club.",
            ],
            'ca' => [
                "Sense {$name} ens costa el doble. Temporada complicada.",
                "Una altra lesionada. Què passa amb els serveis mèdics?",
                "El part mèdic ja és tradició en aquest club.",
            ],
            'gl' => [
                "Sen {$name} cóstanos o dobre. Tempada complicada.",
                "Outra lesionada. Que pasa cos servizos médicos?",
                "O parte médico xa é tradición neste club.",
            ],
            default => $es ? [
                "Sin {$name} nos cuesta el doble. Temporada complicada.",
                "Otra lesionada. ¿Qué pasa con los servicios médicos?",
                "El parte médico ya es tradición en este club.",
            ] : [
                "Without {$name} we're half the team.",
                "Another injury. What's going on?",
            ],
        };

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function seasonTicketTemplates(int $price, bool $expensive, string $lang, bool $es, bool $hasPricing): array
    {
        // B9: respuestas de fans en el idioma del club.
        // B18: sin datos de precios no se menciona ningún importe (no "0 €").
        $positive = match ($lang) {
            'va' => [
                "A este preu m'abone de cap 🎟️",
                "Preus populars, així sí. Ens veiem en la grada.",
                "Ja tinc el meu. A omplir el camp!",
                "El club cuida la seua gent. Gran.",
            ],
            'ca' => [
                "A aquest preu m'abono de cap 🎟️",
                "Preus populars, així sí. Ens veiem a la grada.",
                "Ja tinc el meu. A omplir el camp!",
                "El club cuida la seva gent. Gran.",
            ],
            'gl' => [
                "A este prezo abónome de cabeza 🎟️",
                "Prezos populares, así si. Vémonos na grada.",
                "Xa teño o meu. A encher o campo!",
                "O club coida á súa xente. Grande.",
            ],
            default => $es ? [
                "A este precio me abono de cabeza 🎟️",
                "Precios populares, así sí. Nos vemos en la grada.",
                "Ya tengo el mío. ¡A llenar el campo!",
                "El club cuida a su gente. Grande.",
            ] : [
                "Season ticket secured 🎟️",
                "Fair prices. See you in the stands.",
            ],
        };
        $negativeWithPrice = match ($lang) {
            'va' => [
                "¿{$price} € per l'abonament? Se'ls ha anat l'olla.",
                "Amb estos preus el camp es queda mig buit.",
                "El futbol femení ha de ser accessible, no açò.",
                "Pugen els preus i l'equip ni competix. Quina barra.",
            ],
            'ca' => [
                "¿{$price} € per l'abonament? Se'ls ha anat l'olla.",
                "Amb aquests preus el camp es queda mig buit.",
                "El futbol femení ha de ser accessible, no això.",
                "Pugen els preus i l'equip ni competeix. Quina barra.",
            ],
            'gl' => [
                "¿{$price} € polo abono? Volvéronse tolos.",
                "Con estes prezos o campo queda medio baleiro.",
                "O fútbol feminino ten que ser accesible, non isto.",
                "Suben os prezos e o equipo nin compite. Que cara.",
            ],
            default => $es ? [
                "¿{$price} € por el abono? Se les ha ido la olla.",
                "Con estos precios el campo se queda medio vacío.",
                "El fútbol femenino tiene que ser accesible, no esto.",
                "Suben los precios y el equipo ni compite. Vaya cara.",
            ] : [
                "€{$price} for a season ticket? Outrageous.",
                "At these prices the stands will be half empty.",
            ],
        };
        $negativeNoPricing = match ($lang) {
            'va' => [
                "Diuen que encara no han publicat els preus...",
                "A vore els preus, que el camp cal omplir-lo.",
            ],
            'ca' => [
                "Diuen que encara no han publicat els preus...",
                "A veure els preus, que el camp s'ha d'omplir.",
            ],
            'gl' => [
                "Din que aínda non publicaron os prezos...",
                "A ver os prezos, que o campo hai que enchilo.",
            ],
            default => $es ? [
                "Dicen que aún no han publicado los precios...",
                "A ver los precios, que el campo hay que llenarlo.",
            ] : [
                "Prices aren't even published yet...",
                "Let's see the prices first.",
            ],
        };
        $negative = $hasPricing ? $negativeWithPrice : $negativeNoPricing;

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function nextHomeTemplates(string $lang, bool $es): array
    {
        // B9: respuestas de fans en el idioma del club.
        $positive = match ($lang) {
            'va' => [
                "Ací estaré, com sempre 🏟️",
                "Partit dels que cal guanyar sí o sí.",
                "Quines ganes de tornar a la grada.",
                "A omplir el camp, que es note el suport 💪",
            ],
            'ca' => [
                "Aquí estaré, com sempre 🏟️",
                "Partit dels que cal guanyar sí o sí.",
                "Quines ganes de tornar a la grada.",
                "A omplir el camp, que es noti el suport 💪",
            ],
            'gl' => [
                "Alí estarei, coma sempre 🏟️",
                "Partido dos que hai que gañar si ou si.",
                "Que ganas de volver á grada.",
                "A encher o campo, que se note o apoio 💪",
            ],
            default => $es ? [
                "Ahí estaré, como siempre 🏟️",
                "Partido de los que hay que ganar sí o sí.",
                "Qué ganas de volver a la grada.",
                "A llenar el campo, que se note el apoyo 💪",
            ] : [
                "I'll be there, as always 🏟️",
                "Must-win game. Let's fill the stands 💪",
            ],
        };
        $negative = match ($lang) {
            'va' => [
                "A vore si esta volta sí competim en casa...",
                "L'horari és malíssim, no puc anar.",
            ],
            'ca' => [
                "A veure si aquest cop sí que competim a casa...",
                "L'horari és fatal, no puc anar-hi.",
            ],
            'gl' => [
                "A ver se esta vez si competimos na casa...",
                "O horario é malísimo, non podo ir.",
            ],
            default => $es ? [
                "A ver si esta vez sí competimos en casa...",
                "El horario es malísimo, no puedo ir.",
            ] : [
                "Let's see if we actually compete at home this time...",
            ],
        };

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function ticketDiscountTemplates(string $lang, bool $es): array
    {
        // B9: respuestas de fans en el idioma del club.
        $positive = match ($lang) {
            'va' => [
                "Amb descompte m'apunte segur! 🎟️",
                "Així sí, preus per a la gent.",
                "Ja tinc les meues entrades. Ens veiem allí!",
                "El club pensa en l'afició. Gran 👏",
            ],
            'ca' => [
                "Amb descompte m'hi apunto segur! 🎟️",
                "Així sí, preus per a la gent.",
                "Ja tinc les meves entrades. Ens veiem allà!",
                "El club pensa en l'afició. Gran 👏",
            ],
            'gl' => [
                "Con desconto apúntome seguro! 🎟️",
                "Así si, prezos para a xente.",
                "Xa teño as miñas entradas. Vémonos alí!",
                "O club pensa na afección. Grande 👏",
            ],
            default => $es ? [
                "¡Con descuento me apunto seguro! 🎟️",
                "Así sí, precios para la gente.",
                "Ya tengo mis entradas. ¡Nos vemos allí!",
                "El club piensa en la afición. Grande 👏",
            ] : [
                "Discount secured, I'll be there! 🎟️",
                "Fan-friendly prices. Love it 👏",
            ],
        };
        $negative = match ($lang) {
            'va' => [
                "Un 20% i es creuen generosos...",
                "El descompte no tapa el dels abonaments.",
            ],
            'ca' => [
                "Un 20% i es creuen generosos...",
                "El descompte no tapa el dels abonaments.",
            ],
            'gl' => [
                "Un 20% e créense xenerosos...",
                "O desconto non tapa o dos abonos.",
            ],
            default => $es ? [
                "Un 20% y se creen generosos...",
                "El descuento no tapa lo de los abonos.",
            ] : [
                "20% off and they think they're generous...",
            ],
        };

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function ticketSalesTemplates(string $lang, bool $es): array
    {
        // B9: respuestas de fans en el idioma del club.
        $positive = match ($lang) {
            'va' => [
                "Entrades agafades 🎟️ A pels tres punts!",
                "Ens veiem en la grada, com sempre.",
                "Quines ganes d'este partit.",
            ],
            'ca' => [
                "Entrades agafades 🎟️ A pels tres punts!",
                "Ens veiem a la grada, com sempre.",
                "Quines ganes d'aquest partit.",
            ],
            'gl' => [
                "Entradas collidas 🎟️ A polos tres puntos!",
                "Vémonos na grada, coma sempre.",
                "Que ganas deste partido.",
            ],
            default => $es ? [
                "Entradas pilladas 🎟️ ¡A por los tres puntos!",
                "Nos vemos en la grada, como siempre.",
                "Qué ganas de este partido.",
            ] : [
                "Tickets secured 🎟️",
                "See you in the stands!",
            ],
        };
        $negative = match ($lang) {
            'va' => [
                "Ja a la venda? Ni han dit l'hora...",
                "A vore els preus, que últimament...",
            ],
            'ca' => [
                "Ja a la venda? Ni han dit l'hora...",
                "A veure els preus, que últimament...",
            ],
            'gl' => [
                "Xa á venda? Nin dixeron a hora...",
                "A ver os prezos, que ultimamente...",
            ],
            default => $es ? [
                "¿Ya a la venta? Ni han dicho el horario...",
                "A ver los precios, que últimamente...",
            ] : [
                "On sale already? They haven't even announced kickoff...",
            ],
        };

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function friendlyTemplates(string $lang, bool $es): array
    {
        // B9: respuestas de fans en el idioma del club.
        $positive = match ($lang) {
            'va' => [
                "A vore les noves en acció 👀",
                "La pretemporada il·lusiona, a agafar ritme.",
                "Bon test per a començar.",
            ],
            'ca' => [
                "A veure les noves en acció 👀",
                "La pretemporada il·lusiona, a agafar ritme.",
                "Bon test per començar.",
            ],
            'gl' => [
                "A ver ás novas en acción 👀",
                "A pretempada pinta ben, a coller ritmo.",
                "Bo test para comezar.",
            ],
            default => $es ? [
                "A ver a las nuevas en acción 👀",
                "La pretemporada ilusiona, a coger ritmo.",
                "Buen test para empezar.",
            ] : [
                "Preseason is here, let's see the new faces 👀",
                "Good test to start with.",
            ],
        };
        $negative = match ($lang) {
            'va' => [
                "Els amistosos no em diuen res, la veritat.",
                "Espere que no es lesione ningú...",
            ],
            'ca' => [
                "Els amistosos no em diuen res, la veritat.",
                "Espero que no es lesioni ningú...",
            ],
            'gl' => [
                "Os amigábeis non me din nada, a verdade.",
                "Espero que non se lesione ninguén...",
            ],
            default => $es ? [
                "Los amistosos no me dicen nada, la verdad.",
                "Espero que no se lesione nadie...",
            ] : [
                "Friendlies mean nothing, honestly.",
                "Just hope nobody gets injured...",
            ],
        };

        return [$positive, $negative];
    }

    /** @return array{0:list<string>, 1:list<string>} */
    private function renewalTemplates(?string $name, string $lang, bool $es): array
    {
        // B9: respuestas de fans en el idioma del club.
        $positive = match ($lang) {
            'va' => [
                "Notició! {$name} es queda 💪",
                "Renovació merescudíssima. Gran el club.",
                "Pilar de l'equip, m'alegre un muntó.",
                "Així es construïx un projecte 👏",
            ],
            'ca' => [
                "Notició! {$name} es queda 💪",
                "Renovació merescudíssima. Gran el club.",
                "Pilar de l'equip, me n'alegro un munt.",
                "Així es construeix un projecte 👏",
            ],
            'gl' => [
                "Que boas novas! {$name} queda 💪",
                "Renovación máis que merecida. Grande o club.",
                "Pilar do equipo, alégrome moitísimo.",
                "Así se constrúe un proxecto 👏",
            ],
            default => $es ? [
                "¡Notición! {$name} se queda 💪",
                "Renovación merecidísima. Grande el club.",
                "Pilar del equipo, me alegro un montón.",
                "Así se construye un proyecto 👏",
            ] : [
                "Great news! {$name} stays 💪",
                "Well-deserved renewal 👏",
            ],
        };
        $negative = match ($lang) {
            'va' => [
                "Renovar a {$name}? No ho veig, la veritat.",
                "Eixe sou per a una altra cosa...",
            ],
            'ca' => [
                "Renovar la {$name}? No ho veig, la veritat.",
                "Aquest sou per a una altra cosa...",
            ],
            'gl' => [
                "Renovar a {$name}? Non o vexo, a verdade.",
                "Ese soldo para outra cousa...",
            ],
            default => $es ? [
                "¿Renovar a {$name}? No lo veo, la verdad.",
                "Ese sueldo para otra cosa...",
            ] : [
                "Renewing {$name}? Not convinced...",
            ],
        };

        return [$positive, $negative];
    }
}
