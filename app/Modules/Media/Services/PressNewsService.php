<?php

namespace App\Modules\Media\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
use App\Models\ShortlistedPlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Match\DTOs\MatchNarrative;
use App\Modules\Player\Services\PlayerHistoryService;
use Carbon\Carbon;

/**
 * Press newsroom: full media articles for the dashboard news feed.
 *
 * Complements the pre-match snippets (MatchNarrativeService) and the manager
 * rumour mill (ManagerPressureService) with five new article categories:
 *
 * - sale_rumor:      rival interest in the user's players (real bid records).
 * - signing_rumor:   the user's club linked with incoming players.
 * - preview:         previa of a notable upcoming fixture.
 * - chronicle:       crónica of the last played match (score + scorers).
 * - injury:          parte médico — ONLY when the club has issued the official
 *                    medical statement (ClubSocialService "PARTE MÉDICO" post).
 *                    Without a statement there is no injury news; at most a
 *                    soft injury_rumor ("preocupación por ...").
 *
 * Picks are deterministic per round (crc32 seeds) so the feed is stable
 * across page loads. Articles name only real entities from the game state —
 * never invented players, clubs or diagnoses.
 */
class PressNewsService
{
    public function __construct(
        private readonly MediaOutletService $mediaOutlets,
        private readonly PlayerHistoryService $playerHistory,
    ) {}

    /**
     * @return array<MatchNarrative>
     */
    public function articles(Game $game, ?GameMatch $nextMatch): array
    {
        if ($game->isTournamentMode()) {
            return [];
        }

        $round = (int) ($nextMatch?->round_number ?? 0);
        $articles = [];

        foreach ([
            $nextMatch ? $this->previewArticle($game, $nextMatch, $round) : null,
            $this->chronicleArticle($game, $round),
            $this->injuryArticle($game, $round),
            $this->saleRumorArticle($game, $round),
            $this->signingRumorArticle($game, $round),
        ] as $article) {
            if ($article instanceof MatchNarrative) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    // ── Previa ────────────────────────────────────────────────────────────

    /**
     * Full previa article, only for notable fixtures: cup ties or clashes
     * between top-5 league sides. Quieter league games keep the snippet
     * treatment from MatchNarrativeService.
     */
    private function previewArticle(Game $game, GameMatch $match, int $round): ?MatchNarrative
    {
        $userIsHome = $match->home_team_id === $game->team_id;
        $opponent = $userIsHome ? $match->awayTeam : $match->homeTeam;
        if (! $opponent) {
            return null;
        }

        $notable = $match->isCupMatch();
        $userStanding = GameStanding::forTeamInCompetition($game, $game->team_id, $match->competition_id);
        $oppStanding = GameStanding::forTeamInCompetition($game, $opponent->id, $match->competition_id);
        if (! $notable && $userStanding && $oppStanding
            && $userStanding->position <= 5 && $oppStanding->position <= 5) {
            $notable = true;
        }

        if (! $notable) {
            return null;
        }

        $es = $this->isEs();
        $outlet = $this->outlet($game, $game->id . 'preview' . $round);
        $teamName = $game->team?->name ?? ($es ? 'tu equipo' : 'your team');
        $oppName = $opponent->name;
        $venue = $userIsHome
            ? ($es ? 'en casa' : 'at home')
            : ($es ? 'a domicilio' : 'away');
        $fixture = $userIsHome ? "{$teamName} - {$oppName}" : "{$oppName} - {$teamName}";
        $competition = $match->competition?->shortName() ?? '';
        $homecoming = $this->homecomingLine($game, $match, $es);

        if ($es) {
            $headline = $match->isCupMatch()
                ? "Previa: {$fixture}, con aroma a copa"
                : "Previa: {$fixture}, duelo por todo lo alto";
            $body = [
                "El {$teamName} recibe {$venue} al {$oppName} en un partido que promete. {$competition}",
                $this->previewFormLine($es, $game, $userStanding, $oppStanding, $teamName, $oppName),
                'Los focos estarán puestos en las estrellas de ambos equipos: se espera un duelo de alto voltaje.',
                "Según ha podido saber {$outlet}, el vestuario local afronta la cita con confianza pero sin confianzas.",
            ];
        } else {
            $headline = $match->isCupMatch()
                ? "Preview: {$fixture}, cup fever in the air"
                : "Preview: {$fixture}, a top-of-the-table clash";
            $body = [
                "{$teamName} face {$oppName} {$venue} in a hugely promising fixture. {$competition}",
                $this->previewFormLine($es, $game, $userStanding, $oppStanding, $teamName, $oppName),
                'All eyes will be on both sides\' star players: a high-voltage duel is expected.',
                "According to {$outlet}, the home dressing room approaches the tie with quiet confidence.",
            ];
        }

        if ($homecoming !== null) {
            array_splice($body, 2, 0, [$homecoming]);
        }

        return new MatchNarrative(
            text: $body[0],
            category: 'preview',
            source: $outlet,
            headline: $headline,
            body: $body,
        );
    }

    /**
     * "Vuelve a casa": if a player from either squad faces a former club,
     * the previa highlights the reunion. Deterministic: the highest-rated
     * returner (name as tie-break).
     */
    private function homecomingLine(Game $game, GameMatch $match, bool $es): ?string
    {
        $userTeamId = $game->team_id;
        $opponentId = $match->home_team_id === $userTeamId
            ? $match->away_team_id
            : $match->home_team_id;

        $stories = [];
        foreach ([$userTeamId => $opponentId, $opponentId => $userTeamId] as $teamId => $rivalId) {
            $returner = GamePlayer::where('game_id', $game->id)
                ->where('team_id', $teamId)
                ->orderByDesc('overall_score')
                ->orderBy('name')
                ->get()
                ->first(fn (GamePlayer $p) => $this->playerHistory->returnsHomeAgainst($game, $p, $rivalId));
            if ($returner) {
                $stories[] = $returner;
            }
        }

        if (empty($stories)) {
            return null;
        }

        usort($stories, fn ($a, $b) => [($b->overall_score ?? 0), $a->name] <=> [($a->overall_score ?? 0), $b->name]);
        $star = $stories[0];

        return $es
            ? "El morbo estará en el reencuentro: {$star->name} vuelve a casa frente a su ex-equipo."
            : "The subplot writes itself: {$star->name} returns home to face her former club.";
    }

    private function previewFormLine(
        bool $es,
        Game $game,
        ?GameStanding $userStanding,
        ?GameStanding $oppStanding,
        string $teamName,
        string $oppName,
    ): string {
        if ($userStanding && $oppStanding) {
            return $es
                ? "En la tabla, el {$teamName} es {$this->ordinal($userStanding->position)} con {$userStanding->points} puntos y el {$oppName} {$this->ordinal($oppStanding->position)} con {$oppStanding->points}."
                : "In the table, {$teamName} sit {$this->ordinal($userStanding->position)} on {$userStanding->points} points and {$oppName} {$this->ordinal($oppStanding->position)} on {$oppStanding->points}.";
        }

        return $es
            ? 'Ambos equipos llegan con la moral alta y el objetivo claro: los tres puntos.'
            : 'Both sides arrive in high spirits with one clear objective: all three points.';
    }

    // ── Crónica ───────────────────────────────────────────────────────────

    /**
     * Crónica of the most recent played match involving the user's team:
     * result, scorers and what it means. Deterministic (keyed by match id).
     */
    private function chronicleArticle(Game $game, int $round): ?MatchNarrative
    {
        $teamIds = $game->userTeamIds();
        $last = GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->where(fn ($q) => $q->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds))
            ->orderByDesc('scheduled_date')
            // B13: desempate determinista — si dos partidos (club + filial)
            // comparten fecha, el orden ya no depende del físico de la BD.
            ->orderByDesc('id')
            ->first();

        if (! $last) {
            return null;
        }

        $es = $this->isEs();
        $outlet = $this->outlet($game, $game->id . 'chronicle' . $last->id);
        $userIsHome = in_array($last->home_team_id, $teamIds, true);
        $userTeamId = $userIsHome ? $last->home_team_id : $last->away_team_id;
        $userTeam = Team::find($userTeamId);
        $opponent = $userIsHome ? $last->awayTeam : $last->homeTeam;
        if (! $userTeam || ! $opponent) {
            return null;
        }

        $userScore = $userIsHome ? (int) $last->home_score : (int) $last->away_score;
        $oppScore = $userIsHome ? (int) $last->away_score : (int) $last->home_score;
        $isCup = $last->isCupMatch();
        // M22: la tanda de penaltis decide el partido (no el empate a goles).
        $userPen = $userIsHome ? $last->home_score_penalties : $last->away_score_penalties;
        $oppPen = $userIsHome ? $last->away_score_penalties : $last->home_score_penalties;
        $shootout = $userPen !== null && $oppPen !== null;

        if ($shootout) {
            $penSuffix = $es
                ? " ({$userPen}-{$oppPen} en los penaltis)"
                : " ({$userPen}-{$oppPen} on penalties)";
            $score = "{$userScore}-{$oppScore}{$penSuffix}";
            $resultWord = $userPen > $oppPen ? ($es ? 'victoria' : 'win') : ($es ? 'derrota' : 'defeat');
        } else {
            $score = "{$userScore}-{$oppScore}";
            $resultWord = $userScore > $oppScore ? ($es ? 'victoria' : 'win')
                : ($userScore < $oppScore ? ($es ? 'derrota' : 'defeat') : ($es ? 'empate' : 'draw'));
        }
        $teamName = $userTeam->name;
        $oppName = $opponent->name;

        $scorers = $this->scorerLines($last, $userTeamId, $es);

        if ($es) {
            $headline = "Crónica: {$teamName} {$score} {$oppName}";
            $body = [
                "{$resultWord} del {$teamName} por {$score} ante el {$oppName}.",
                $scorers,
                $this->chronicleMeaningLine($es, $userScore, $oppScore, $teamName, $isCup, $userPen, $oppPen),
                "Lo analizamos en {$outlet}: el vestuario ya piensa en la próxima cita.",
            ];
        } else {
            $headline = "Report: {$teamName} {$score} {$oppName}";
            $body = [
                "{$teamName} {$resultWord} {$score} against {$oppName}.",
                $scorers,
                $this->chronicleMeaningLine($es, $userScore, $oppScore, $teamName, $isCup, $userPen, $oppPen),
                "Analysis on {$outlet}: the dressing room is already thinking about the next fixture.",
            ];
        }

        // B12: la crónica empezaba en minúscula ("victoria del X…"). Capitalizar
        // el primer cuerpo de forma multibyte-segura, en ambos idiomas.
        $body[0] = mb_strtoupper(mb_substr($body[0], 0, 1)) . mb_substr($body[0], 1);

        return new MatchNarrative(
            text: $body[0],
            category: 'chronicle',
            source: $outlet,
            headline: $headline,
            body: array_values(array_filter($body)),
        );
    }

    /**
     * "Goles de X (23') y de Y (67')" from the match events, user's team only.
     */
    private function scorerLines(GameMatch $match, string $userTeamId, bool $es): string
    {
        $goals = MatchEvent::where('game_match_id', $match->id)
            ->whereIn('event_type', [MatchEvent::TYPE_GOAL, MatchEvent::TYPE_OWN_GOAL])
            ->orderBy('minute')
            ->get()
            ->filter(function (MatchEvent $e) use ($userTeamId) {
                // Own goals are credited against the scorer's team; keep only
                // goals that went in the user's team's favour.
                if ($e->event_type === MatchEvent::TYPE_OWN_GOAL) {
                    return $e->team_id !== $userTeamId;
                }

                return $e->team_id === $userTeamId || $e->team_id === null;
            })
            ->values();

        if ($goals->isEmpty()) {
            return $es
                ? 'Los goles se hicieron esperar, pero el marcador no se movió más.'
                : 'Goals were hard to come by after that.';
        }

        $parts = [];
        foreach ($goals->take(3) as $goal) {
            $name = $goal->player_name ?: ($es ? 'una jugadora local' : 'a home player');
            $parts[] = "{$name} ({$goal->minute}')";
        }

        $more = $goals->count() > 3 ? ($es ? ' y más' : ' and more') : '';

        return $es
            ? 'Goles de ' . implode(', ', $parts) . $more . '.'
            : 'Goals from ' . implode(', ', $parts) . $more . '.';
    }

    /**
     * M19/M21/M22: la línea de "qué significa" distingue copa de liga (en
     * copa no hay puntos en juego) y tiene en cuenta la tanda de penaltis.
     */
    private function chronicleMeaningLine(
        bool $es,
        int $userScore,
        int $oppScore,
        string $teamName,
        bool $isCup,
        ?int $userPen,
        ?int $oppPen,
    ): string {
        // M22: el partido se decidió en los penaltis, no fue un empate.
        if ($userPen !== null && $oppPen !== null) {
            return $userPen > $oppPen
                ? ($es
                    ? "El {$teamName} se impone en la tanda de penaltis ({$userPen}-{$oppPen}) y sigue adelante."
                    : "{$teamName} hold their nerve from the spot ({$userPen}-{$oppPen}) and march on.")
                : ($es
                    ? "El {$teamName} cae en la tanda de penaltis ({$userPen}-{$oppPen}) y dice adiós."
                    : "{$teamName} fall in the shootout ({$userPen}-{$oppPen}) and bow out.");
        }

        // M19/M21: en copa no se reparten puntos.
        if ($isCup) {
            if ($userScore > $oppScore) {
                return $es
                    ? "El {$teamName} saca adelante la eliminatoria y sigue soñando."
                    : "{$teamName} come through the tie and keep dreaming.";
            }
            if ($userScore < $oppScore) {
                return $es
                    ? "Duro golpe para el {$teamName} en la copa, obligado a reaccionar."
                    : "A tough blow for {$teamName} in the cup, who must react now.";
            }

            return $es
                ? "Empate que deja la eliminatoria completamente abierta."
                : "A draw that leaves the tie wide open.";
        }

        if ($userScore > $oppScore) {
            return $es
                ? "Tres puntos de oro para el {$teamName}, que sigue creciendo."
                : "Three golden points for {$teamName}, who keep growing.";
        }
        if ($userScore < $oppScore) {
            return $es
                ? "Duro golpe para el {$teamName}, obligado a reaccionar ya."
                : "A tough blow for {$teamName}, who must react now.";
        }

        return $es
            ? "Reparto de puntos que deja al {$teamName} con sensaciones mixtas."
            : "A point apiece leaves {$teamName} with mixed feelings.";
    }

    // ── Lesiones: solo con comunicado oficial ─────────────────────────────

    /**
     * Injury news is ONLY published when the club has issued the official
     * medical statement (ClubSocialService "PARTE MÉDICO" post). Without a
     * statement there is no injury news — at most a soft rumour.
     */
    private function injuryArticle(Game $game, int $round): ?MatchNarrative
    {
        $now = $game->current_date ? Carbon::parse($game->current_date) : Carbon::now();

        // Injuries live on the player's match-state satellite row.
        $injured = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereHas('matchState', fn ($q) => $q
                ->whereNotNull('injury_until')
                ->where('injury_until', '>=', $now->toDateString()))
            ->with('matchState')
            ->get()
            ->sortBy(fn (GamePlayer $p) => $p->injury_until?->toDateString())
            ->values();

        if ($injured->isEmpty()) {
            return null;
        }

        // Deterministic pick so the feed doesn't flicker.
        $player = $injured->values()[(crc32($game->id . 'injury' . $round) & 0x7FFFFFFF) % $injured->count()];
        $es = $this->isEs();
        $outlet = $this->outlet($game, $game->id . 'injury' . $round);
        $teamName = $game->team?->name ?? ($es ? 'el club' : 'the club');

        if ($this->hasOfficialInjuryStatement($game, $player)) {
            $weeks = max(1, (int) ceil($now->diffInDays(Carbon::parse($player->injury_until)) / 7));
            $injuryEs = $this->injuryTypeEs($player->injury_type);

            if ($es) {
                $headline = "Parte médico: {$player->name}, {$weeks} " . ($weeks === 1 ? 'semana' : 'semanas') . ' de baja';
                $body = [
                    "El {$teamName} ha emitido el parte médico de {$player->name}: {$injuryEs} y unas {$weeks} " . ($weeks === 1 ? 'semana' : 'semanas') . ' de baja.',
                    'El club no forzará los plazos: la prioridad es que vuelva al cien por cien.',
                    "Según ha podido saber {$outlet}, el cuerpo técnico ya trabaja en alternativas para cubrir su ausencia.",
                    '¡Mucho ánimo! La afición la espera de vuelta.',
                ];
            } else {
                $headline = "Medical report: {$player->name} out for {$weeks} " . ($weeks === 1 ? 'week' : 'weeks');
                $body = [
                    "{$teamName} have issued the medical report on {$player->name}: {$player->injury_type} — out for around {$weeks} " . ($weeks === 1 ? 'week' : 'weeks') . '.',
                    'The club will not rush her back: the priority is a full recovery.',
                    "According to {$outlet}, the coaching staff are already working on alternatives.",
                    'Get well soon — the fans are waiting for her return.',
                ];
            }

            return new MatchNarrative(
                text: $body[0],
                category: 'injury',
                source: $outlet,
                headline: $headline,
                body: $body,
            );
        }

        // No official statement: at most a rumour, never a diagnosis.
        // Deterministic coin flip per round so it doesn't flicker.
        if (crc32($game->id . 'injuryrumor' . $round) % 2 !== 0) {
            return null;
        }

        if ($es) {
            $headline = "Preocupación en el {$teamName} por {$player->name}";
            $body = [
                "Hay preocupación en el {$teamName}: {$player->name} terminó el último partido con molestias.",
                'El club aún no ha emitido parte médico oficial, así que no hay diagnóstico confirmado.',
                "Según ha podido saber {$outlet}, se le harán pruebas en las próximas horas.",
            ];
        } else {
            $headline = "Concern at {$teamName} over {$player->name}";
            $body = [
                "Concern at {$teamName}: {$player->name} finished the last match with discomfort.",
                'The club has not issued an official medical report, so there is no confirmed diagnosis.',
                "According to {$outlet}, she will undergo tests in the coming hours.",
            ];
        }

        return new MatchNarrative(
            text: $body[0],
            category: 'injury_rumor',
            source: $outlet,
            headline: $headline,
            body: $body,
        );
    }

    /**
     * Has the club published the official medical report for this player?
     * (ClubSocialService "PARTE MÉDICO" / "MEDICAL REPORT" announcement.)
     *
     * M20: match EXACTO por jugadora, nunca LIKE '%nombre%': un LIKE casa
     * "Ana" con el parte escrito para "Ana María" y desbloqueaba su
     * diagnóstico sin comunicado propio. Los comunicados nuevos llevan
     * game_player_id; los antiguos (sin referencia) se reconocen con el
     * nombre anclado al verbo de la plantilla ("estará"/"estarà"/"will be"),
     * que "Ana" no casa en "Ana María estará…".
     */
    private function hasOfficialInjuryStatement(Game $game, GamePlayer $player): bool
    {
        $exact = SocialPost::where('game_id', $game->id)
            ->where('context', 'club_official')
            ->where('post_kind', ClubSocialService::TYPE_INJURY)
            ->where('game_player_id', $player->id)
            ->exists();
        if ($exact) {
            return true;
        }

        // Legacy: comunicados publicados antes de guardar la referencia.
        $name = trim((string) $player->name);
        if ($name === '') {
            return false;
        }
        $pattern = '/' . preg_quote($name, '/') . ' (estará|estarà|will be) /u';

        return SocialPost::where('game_id', $game->id)
            ->where('context', 'club_official')
            ->where('text', 'like', '%🏥%')
            ->whereNull('game_player_id')
            ->pluck('text')
            ->contains(fn (string $text) => (bool) preg_match($pattern, $text));
    }

    private function injuryTypeEs(?string $type): string
    {
        $map = [
            'Muscle strain' => 'una rotura muscular',
            'Muscle fatigue' => 'fatiga muscular',
            'Ligament damage' => 'daños en los ligamentos',
            'Ankle sprain' => 'un esguince de tobillo',
            'Knee injury' => 'una lesión de rodilla',
            'Calf strain' => 'una sobrecarga en el gemelo',
            'Groin strain' => 'una distensión en la ingle',
            // M6: estos 5 tipos existen en InjuryService::INJURY_TYPES pero
            // caían en el genérico "una lesión muscular" (un cruzado no es
            // una lesión muscular). Vocabulario alineado con lang/es/squad.php.
            'Hamstring tear' => 'una rotura de isquiotibial',
            'Knee contusion' => 'una contusión de rodilla',
            'Metatarsal fracture' => 'una fractura de metatarso',
            'ACL tear' => 'una rotura del ligamento cruzado',
            'Achilles rupture' => 'una rotura del tendón de Aquiles',
        ];

        return $map[$type ?? ''] ?? 'una lesión muscular';
    }

    // ── Rumores de ventas ─────────────────────────────────────────────────

    /**
     * Sale rumour article from the biggest REAL active bid for one of the
     * user's players (unsolicited / pre-contract offers). Fresh bids lead.
     */
    private function saleRumorArticle(Game $game, int $round): ?MatchNarrative
    {
        $offer = TransferOffer::with(['gamePlayer', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->ofType(TransferOffer::TYPE_UNSOLICITED, TransferOffer::TYPE_PRE_CONTRACT)
            ->active()
            ->departingFrom($game->userTeamIds())
            ->orderByDesc('transfer_fee')
            ->first();

        if (! $offer || ! $offer->gamePlayer?->name || ! $offer->offeringTeam?->name) {
            return null;
        }

        // A bid is "breaking news" when fresh and fades as it lingers.
        $ageDays = $offer->game_date ? abs($offer->game_date->diffInDays($game->current_date)) : 999;
        if ($ageDays > 21) {
            return null;
        }

        $es = $this->isEs();
        $outlet = $this->outlet($game, $game->id . 'salerumor' . $round);
        $playerName = $offer->gamePlayer->name;
        $clubName = $offer->offeringTeam->name;
        $teamName = $game->team?->name ?? ($es ? 'el club' : 'the club');
        $fee = $offer->formatted_transfer_fee;

        if ($es) {
            $headline = "El {$clubName} va en serio a por {$playerName}";
            $body = [
                "El {$clubName} ha puesto {$fee} sobre la mesa por {$playerName}, del {$teamName}.",
                'La jugadora no ha querido hacer declaraciones, pero su entorno no desmiente el interés.',
                "Según ha podido saber {$outlet}, la oferta ha llegado a las oficinas y se estudiará en los próximos días.",
                'La afición cruza los dedos: es una de las piezas clave del proyecto.',
            ];
        } else {
            $headline = "{$clubName} make their move for {$playerName}";
            $body = [
                "{$clubName} have put {$fee} on the table for {$playerName} of {$teamName}.",
                'The player declined to comment, but her camp is not denying the interest.',
                "According to {$outlet}, the bid has landed on the board's desk and will be studied in the coming days.",
                'The fans are holding their breath: she is a key piece of the project.',
            ];
        }

        return new MatchNarrative(
            text: $body[0],
            category: 'sale_rumor',
            source: $outlet,
            headline: $headline,
            body: $body,
        );
    }

    // ── Rumores de fichajes ───────────────────────────────────────────────

    /**
     * Signing rumour: the user's own pending bids first (real intent), then
     * shortlisted players ("en la agenda del club").
     */
    private function signingRumorArticle(Game $game, int $round): ?MatchNarrative
    {
        $es = $this->isEs();
        $outlet = $this->outlet($game, $game->id . 'signingrumor' . $round);
        $teamName = $game->team?->name ?? ($es ? 'el club' : 'the club');

        $bid = TransferOffer::with(['gamePlayer', 'sellingTeam'])
            ->where('game_id', $game->id)
            ->ofType(TransferOffer::TYPE_USER_BID)
            ->active()
            ->where('offering_team_id', $game->team_id)
            ->orderByDesc('transfer_fee')
            ->first();

        if ($bid && $bid->gamePlayer?->name) {
            $playerName = $bid->gamePlayer->name;
            $sellerName = $bid->sellingTeam?->name ?? ($es ? 'su club' : 'her club');
            $fee = $bid->formatted_transfer_fee;

            if ($es) {
                $headline = "El {$teamName} negocia por {$playerName}";
                $body = [
                    "El {$teamName} va en serio a por {$playerName}, del {$sellerName}: oferta de {$fee}.",
                    'Las negociaciones están en marcha y hay optimismo en ambas partes.',
                    "Según ha podido saber {$outlet}, la jugadora vería con buenos ojos el cambio de aires.",
                    'La dirección deportiva quiere cerrar la operación cuanto antes.',
                ];
            } else {
                $headline = "{$teamName} in talks for {$playerName}";
                $body = [
                    "{$teamName} mean business for {$playerName} of {$sellerName}: a {$fee} bid.",
                    'Negotiations are under way and there is optimism on both sides.',
                    "According to {$outlet}, the player would welcome the move.",
                    'The sporting directors want to close the deal as soon as possible.',
                ];
            }

            return new MatchNarrative(
                text: $body[0],
                category: 'signing_rumor',
                source: $outlet,
                headline: $headline,
                body: $body,
            );
        }

        // Fallback: a shortlisted player, deterministic per round.
        $shortlisted = ShortlistedPlayer::where('game_id', $game->id)
            ->with('gamePlayer')
            ->get()
            ->filter(fn (ShortlistedPlayer $s) => $s->gamePlayer?->name)
            ->values();

        if ($shortlisted->isEmpty()) {
            return null;
        }

        $pick = $shortlisted[(crc32($game->id . 'shortlist' . $round) & 0x7FFFFFFF) % $shortlisted->count()];
        $playerName = $pick->gamePlayer->name;

        if ($es) {
            $headline = "{$playerName}, en la agenda del {$teamName}";
            $body = [
                "{$playerName} ha entrado en la agenda del {$teamName} para reforzar la plantilla.",
                'De momento solo es seguimiento: no hay oferta formal sobre la mesa.',
                "Según ha podido saber {$outlet}, los ojeadores han enviado informes muy positivos.",
            ];
        } else {
            $headline = "{$playerName} on {$teamName}'s radar";
            $body = [
                "{$playerName} is on {$teamName}'s radar as a potential reinforcement.",
                'For now it is only monitoring: no formal bid on the table.',
                "According to {$outlet}, the scouts have sent back glowing reports.",
            ];
        }

        return new MatchNarrative(
            text: $body[0],
            category: 'signing_rumor',
            source: $outlet,
            headline: $headline,
            body: $body,
        );
    }

    // ── Utilidades ────────────────────────────────────────────────────────

    private function isEs(): bool
    {
        return app()->getLocale() === 'es';
    }

    /**
     * Deterministic outlet for an article seed — the feed must not flicker
     * across page loads.
     */
    private function outlet(Game $game, string $seed): string
    {
        return $this->mediaOutlets->deterministicOutlet($game, $seed);
    }

    private function ordinal(int $position): string
    {
        if ($this->isEs()) {
            return $position . '.º';
        }

        $suffix = match (true) {
            $position % 100 >= 11 && $position % 100 <= 13 => 'th',
            $position % 10 === 1 => 'st',
            $position % 10 === 2 => 'nd',
            $position % 10 === 3 => 'rd',
            default => 'th',
        };

        return $position . $suffix;
    }
}
