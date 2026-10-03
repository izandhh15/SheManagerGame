<?php

namespace App\Modules\Media\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameJournalist;
use App\Models\GameMatch;
use App\Models\SocialPost;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

/**
 * Fictional journalists covering the manager's career on the fake social
 * network ("X" clone).
 *
 * Every account is invented on purpose: no real people, no real outlets, no
 * "MARCA"/"AS"/"Sport" style brands. Journalists post neutral (sentiment 0)
 * news-style tweets about the game's actuality: signings, results, board
 * rumours. Players interact with them through the existing social actions
 * (likes; the hater-reply flow only applies to negative fan posts).
 */
class JournalistService
{
    /**
     * The newsroom. Each entry: [name, handle, specialty, base_followers].
     * All invented — none of them are real sports journalists.
     */
    public const ROSTER = [
        ['Lucía Ferrán', '@luciaferran', 'fichajes', 42000],
        ['Marcos Téllez', '@marco_tellez', 'cronicas', 31000],
        ['Paula Rincón', '@paularincon', 'rumores', 27500],
        ['Pablo Sendín', '@pablosendin', 'internacional', 21000],
        ['Diego Alcover', '@diegoalcover', 'tactica', 19800],
        ['Carla Monzó', '@carlamonzo', 'datos', 18600],
        ['Iván Cester', '@ivancester', 'mercado', 17200],
        ['Nora Vidal', '@noravidal', 'cantera', 15400],
    ];

    /**
     * Create the journalist accounts for a new game. Idempotent.
     *
     * @return Collection<int, GameJournalist>
     */
    public function seedFor(Game $game): Collection
    {
        if (GameJournalist::where('game_id', $game->id)->exists()) {
            return GameJournalist::where('game_id', $game->id)->get();
        }

        foreach (self::ROSTER as [$name, $handle, $specialty, $followers]) {
            GameJournalist::create([
                'game_id' => $game->id,
                'name' => $name,
                'handle' => $handle,
                'specialty' => $specialty,
                'followers' => $followers + rand(-3000, 3000),
                'active' => true,
            ]);
        }

        return GameJournalist::where('game_id', $game->id)->get();
    }

    /**
     * Intro tweets when the newsroom opens for business.
     */
    public function publishWelcome(Game $game): void
    {
        $es = $this->isEs();
        $teamName = $game->team?->name ?? ($es ? 'tu club' : 'your club');

        $lucia = $this->pick($game, 'fichajes');
        if ($lucia) {
            $text = $es
                ? "🚨 ¡Hola, mundo! Soy Lucía Ferrán y a partir de hoy os cuento TODOS los fichajes de la temporada. Activad las notificaciones... esto se va a poner interesante 👀"
                : "🚨 Hi world! I'm Lucía Ferrán and from today I'll bring you EVERY transfer this season. Turn on notifications... this is going to get interesting 👀";
            $this->post($game, $lucia, $text, 'journalist_welcome');
        }

        $marcos = $this->pick($game, 'cronicas');
        if ($marcos && rand(0, 1)) {
            $text = $es
                ? "🎙️ Marcos Téllez por aquí. Seguiré de cerca la temporada de {$teamName}: crónicas, protagonistas y algún que otro zasca con cariño. ¡Que ruede el balón! ⚽"
                : "🎙️ Marcos Téllez here. I'll be following {$teamName}'s season closely: reports, key players and the odd gentle jab. Let's get this ball rolling! ⚽";
            $this->post($game, $marcos, $text, 'journalist_welcome');
        }
    }

    /**
     * Match report after a finalized match involving the manager's team.
     * No-op unless the match involves the user's club (or its reserve team)
     * and journalists were seeded for the game.
     */
    public function postMatchReport(Game $game, GameMatch $match, ?Competition $competition = null): ?SocialPost
    {
        $managedIds = array_filter([$game->team_id, $game->reserve_team_id]);
        $involved = in_array($match->home_team_id, $managedIds, true)
            || in_array($match->away_team_id, $managedIds, true);

        if (! $involved) {
            return null;
        }

        $journalist = $this->pick($game, 'cronicas');
        if (! $journalist) {
            return null;
        }

        $home = Team::find($match->home_team_id)?->name ?? ($this->isEs() ? 'Local' : 'Home');
        $away = Team::find($match->away_team_id)?->name ?? ($this->isEs() ? 'Visitante' : 'Away');
        $hs = $match->home_score ?? 0;
        $as = $match->away_score ?? 0;
        $comp = $competition?->name;

        $userWon = in_array($match->getWinnerId(), $managedIds, true);
        $draw = $hs === $as;

        $es = $this->isEs();
        $headline = $es ? "FINAL: {$home} {$hs}-{$as} {$away}." : "FULL-TIME: {$home} {$hs}-{$as} {$away}.";

        if ($es) {
            $flavor = $draw
                ? ["Reparto de puntos y de bostezos... o no. ¡Vaya partido!", "Tablas en el marcador. El míster sale vivo, que no es poco."]
                : ($userWon
                    ? ["¡Fiesta en la grada! El proyecto del míster va cogiendo forma 💪", "Tres puntos que saben a gloria. La afición ya corea su nombre 🎶"]
                    : ["Noche para olvidar. El vestuario tiene trabajo por delante 😬", "Derrota dura. Mañana toca rueda de prensa... y no será cómoda 🍿"]);
            $tail = $comp ? " ({$comp})" : '';
        } else {
            $flavor = $draw
                ? ["Points shared. The gaffer lives to fight another day.", "All square. Not a classic, but not a disaster either."]
                : ($userWon
                    ? ["Party time in the stands! The gaffer's project is taking shape 💪", "Three points that taste like glory. The fans are already chanting 🎶"]
                    : ["A night to forget. Work to do in the dressing room 😬", "Tough defeat. Tomorrow's press conference won't be comfortable 🍿"]);
            $tail = $comp ? " ({$comp})" : '';
        }

        $text = $headline.' '.$flavor[array_rand($flavor)].$tail;

        // National-team reports are signed by a real media outlet.
        $isNational = ($game->team?->type ?? 'club') === 'national';
        $outlet = $isNational ? $this->pickOutlet($game) : null;

        return $outlet
            ? $this->postAsOutlet($game, $outlet, $text, 'journalist_match', $match->id)
            : $this->post($game, $journalist, $text, 'journalist_match', $match->id);
    }

    /**
     * National-team match preview: a journalist tweets the upcoming fixture.
     * Only posts for national-team saves; idempotent per match (the caller
     * should use maybePostNationalPreview() to avoid double-posting).
     */
    public function postMatchPreview(Game $game, GameMatch $match): ?SocialPost
    {
        $team = $game->team;
        if (! $team || ($team->type ?? 'club') !== 'national') {
            return null;
        }

        // National press is signed by a real media outlet (MARCA, 433, ...),
        // falling back to the fictional newsroom if none resolves.
        $outlet = $this->pickOutlet($game);
        $journalist = $outlet ? null : $this->pick($game, 'cronicas');
        if (! $outlet && ! $journalist) {
            return null;
        }

        $home = Team::find($match->home_team_id)?->name ?? ($this->isEs() ? 'Local' : 'Home');
        $away = Team::find($match->away_team_id)?->name ?? ($this->isEs() ? 'Visitante' : 'Away');
        $userTeam = $team->name;
        $rival = $match->home_team_id === $team->id ? $away : $home;
        $isHome = $match->home_team_id === $team->id;

        $competition = $match->competition;
        $comp = $competition?->name;
        $isFriendly = $competition && $competition->handler_type === 'friendly';

        $venue = $match->stadium_name ?: $match->neutral_venue_name;
        $date = $match->scheduled_date
            ? ucfirst($match->scheduled_date->translatedFormat('D d M'))
            : '';

        $es = $this->isEs();
        if ($es) {
            $headline = "🔜 PREVIA | {$home} vs {$away}";
            $meta = implode(' · ', array_filter([
                $comp ? "🏆 {$comp}" : null,
                $date ? "📅 {$date}" : null,
                $venue ? "🏟️ {$venue}" : null,
            ]));
            $storylines = $isFriendly
                ? [
                    "Amistoso de prestigio para la {$userTeam}: minutos, pruebas y ritmo antes de lo serio.",
                    "La {$userTeam} aprovecha el parón para probarse ante {$rival}. Sin puntos en juego, pero con mucho que ganar.",
                ]
                : [
                    "La {$userTeam} vuelve al parón con todo en juego. " . ($isHome ? "El {$venue} aprieta desde la grada." : "Toca sufrir fuera de casa."),
                    "Duelo {$home} vs {$away} con aroma a grande. La {$userTeam}, a por todas.",
                    "Prueba de fuego para la {$userTeam} ante {$rival}. Que ruede el balón ⚽",
                ];
            $text = $headline . "\n" . $meta . "\n\n" . $storylines[array_rand($storylines)];
        } else {
            $headline = "🔜 PREVIEW | {$home} vs {$away}";
            $meta = implode(' · ', array_filter([
                $comp ? "🏆 {$comp}" : null,
                $date ? "📅 {$date}" : null,
                $venue ? "🏟️ {$venue}" : null,
            ]));
            $storylines = $isFriendly
                ? [
                    "Prestige friendly for {$userTeam}: minutes, experiments and rhythm before the real stuff.",
                    "{$userTeam} use the break to test themselves against {$rival}. No points at stake, but plenty to gain.",
                ]
                : [
                    "{$userTeam} return to international duty with everything on the line.",
                    "{$home} vs {$away} has all the makings of a classic. {$userTeam} going all in.",
                    "A real test for {$userTeam} against {$rival}. Let the ball roll ⚽",
                ];
            $text = $headline . "\n" . $meta . "\n\n" . $storylines[array_rand($storylines)];
        }

        return $outlet
            ? $this->postAsOutlet($game, $outlet, $text, 'journalist_preview', $match->id)
            : $this->post($game, $journalist, $text, 'journalist_preview', $match->id);
    }

    /**
     * Post a preview for the user's national team upcoming match (the day
     * before) if none was posted yet. Silent no-op for club saves.
     */
    public function maybePostNationalPreview(Game $game): ?SocialPost
    {
        $team = $game->team;
        if (! $team || ($team->type ?? 'club') !== 'national') {
            return null;
        }

        $now = $game->current_date ?? now();
        $teamId = $team->id;

        // The preview drops the day before the match. As a fallback, it
        // also fires on match day itself (when the advance lands directly
        // on the fixture without stopping the day before).
        $match = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId);
            })
            ->whereDate('scheduled_date', '>=', $now->copy()->toDateString())
            ->whereDate('scheduled_date', '<=', $now->copy()->addDay()->toDateString())
            ->orderBy('scheduled_date')
            ->first();

        if (! $match) {
            return null;
        }

        $already = SocialPost::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->where('context', 'journalist_preview')
            ->exists();

        if ($already) {
            return null;
        }

        return $this->postMatchPreview($game, $match);
    }

    /**
     * Transfer news: 'in' (user buys), 'out' (user sells), 'free' (free agent).
     */
    public function postTransferNews(Game $game, string $playerName, string $fromTeam, string $toTeam, string $kind): ?SocialPost
    {
        $journalist = $this->pick($game, 'fichajes');
        if (! $journalist) {
            return null;
        }

        $es = $this->isEs();

        $text = match ($kind) {
            'out' => $es
                ? "🚨 OFICIAL: {$playerName} deja {$fromTeam} y pone rumbo a {$toTeam}. La grada ya la echa de menos... y el míster tendrá que rearmar el puzzle 🧩"
                : "🚨 OFFICIAL: {$playerName} leaves {$fromTeam} for {$toTeam}. The stands already miss her... and the gaffer must rebuild the puzzle 🧩",
            'free' => $es
                ? "✍️ ¡FICHAJE A COSTE CERO! {$playerName} se compromete con {$toTeam}. Negocio redondo del míster: calidad sin pasar por caja 💰"
                : "✍️ DONE DEAL ON A FREE! {$playerName} signs for {$toTeam}. Shrewd business from the gaffer: quality without spending a penny 💰",
            default => $es
                ? "🚨 ¡BOMBAZO! {$playerName} es nueva jugadora de {$toTeam} procedente de {$fromTeam}. Menudo refuerzo se ha sacado el míster de la chistera 🎩✨"
                : "🚨 DONE DEAL! {$playerName} joins {$toTeam} from {$fromTeam}. What a signing the gaffer has pulled out of the hat 🎩✨",
        };

        return $this->post($game, $journalist, $text, 'journalist_transfer');
    }

    /**
     * Board-room rumour when confidence in the manager wobbles.
     */
    public function postBoardRumor(Game $game, string $teamName): ?SocialPost
    {
        $journalist = $this->pick($game, 'rumores');
        if (! $journalist) {
            return null;
        }

        $es = $this->isEs();
        $texts = $es ? [
            "👀 Me cuentan desde dentro que en {$teamName} ya suenan nombres para el banquillo... El míster, de momento, ni se inmuta. Veremos.",
            "🤐 Rumor de vestuario: la directiva de {$teamName} empieza a mirar de reojo al banquillo. La próxima jornada puede ser decisiva.",
            "📻 En la tertulia de esta mañana: ¿aguanta el míster de {$teamName} hasta Navidad? Las apuestas están que arden 🔥",
        ] : [
            "👀 Word from inside: {$teamName}'s board is already sounding out names for the dugout... The gaffer, for now, unfazed. We'll see.",
            "🤐 Dressing-room rumour: {$teamName}'s board is starting to glance at the dugout. Next matchday could be decisive.",
            "📻 This morning's talk show: does {$teamName}'s gaffer survive until Christmas? The betting is on fire 🔥",
        ];

        return $this->post($game, $journalist, $texts[array_rand($texts)], 'journalist_rumor');
    }

    /**
     * Occasional transfer rumour to keep the timeline alive between windows.
     */
    public function postTransferRumor(Game $game, string $playerName, string $linkedTeam): ?SocialPost
    {
        $journalist = $this->pick($game, 'mercado');
        if (! $journalist) {
            return null;
        }

        $es = $this->isEs();
        $texts = $es ? [
            "🔥 RUMOR: {$linkedTeam} sigue muy de cerca a {$playerName}. Aún no hay oferta, pero el interés es REAL. Atentos...",
            "👀 Ojo con esto: me dicen que {$playerName} gusta, y mucho, en {$linkedTeam}. El mercado se calienta 🔥",
        ] : [
            "🔥 RUMOUR: {$linkedTeam} are closely tracking {$playerName}. No bid yet, but the interest is REAL. Stay tuned...",
            "👀 One to watch: I'm told {$playerName} is highly rated at {$linkedTeam}. The market is heating up 🔥",
        ];

        return $this->post($game, $journalist, $texts[array_rand($texts)], 'journalist_rumor');
    }

    /**
     * Pick an active journalist for the game, preferring a specialty.
     */
    public function pick(Game $game, ?string $specialty = null): ?GameJournalist
    {
        $journalists = GameJournalist::where('game_id', $game->id)
            ->where('active', true)
            ->get();

        if ($journalists->isEmpty()) {
            return null;
        }

        if ($specialty) {
            $match = $journalists->firstWhere('specialty', $specialty);
            if ($match) {
                return $match;
            }
        }

        return $journalists->random();
    }

    private function post(Game $game, GameJournalist $journalist, string $text, string $context, ?string $matchId = null): SocialPost
    {
        return SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $journalist->name,
            'author_handle' => $journalist->handle,
            'journalist_id' => $journalist->id,
            'text' => $text,
            'sentiment' => 0,
            // Journalists have followers: bigger splash than a fan post.
            'likes' => rand(200, min(3000, max(300, (int) ($journalist->followers / 25)))),
            'context' => $context,
            'match_id' => $matchId,
        ]);
    }

    /**
     * Post as a real media outlet (MARCA, 433, OneFootball, ...) instead of
     * a fictional journalist. Used for national-team press.
     */
    private function postAsOutlet(Game $game, string $outlet, string $text, string $context, ?string $matchId = null): SocialPost
    {
        $handle = '@' . strtolower(preg_replace('/[^a-z0-9]/i', '', $outlet));

        return SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $outlet,
            'author_handle' => $handle,
            'journalist_id' => null,
            'text' => $text,
            'sentiment' => 0,
            'likes' => rand(500, 8000),
            'context' => $context,
            'match_id' => $matchId,
        ]);
    }

    /**
     * Pick a media outlet relevant for this game (country press + international).
     */
    private function pickOutlet(Game $game): ?string
    {
        try {
            $outlet = app(MediaOutletService::class)->randomOutlet($game);
            return $outlet ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isEs(): bool
    {
        return app()->getLocale() === 'es';
    }
}
