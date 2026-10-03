<?php

namespace App\Modules\Match\Listeners;

use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\GameMatch;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Season\Services\SeasonInitializationService;
use Illuminate\Support\Facades\Log;

/**
 * Routes UEFA qualifying playoff results into the competitions they feed.
 *
 * - UCLQ winner → UWCL league phase; UCLQ loser → drops into UELQ round 2
 *   (the real UWCL → UWEC drop-down: e.g. Juventus out in qualifying
 *   plays the Europa Cup from the second qualifying round).
 * - UELQ round-1 winner → stays in UELQ (their entry already covers
 *   round 2); UELQ round-2 winner → Europa Cup knockout (round of 16).
 *   UELQ losers of any round are out.
 *
 * When the qualifying playoffs are decided, initializes what they feed:
 * the deferred UWCL Swiss league phase, the UELQ round-2 draw, and the
 * Europa Cup round-of-16 draw (in that dependency order).
 */
class RouteQualifyingResultsListener
{
    public function __construct(
        private SeasonInitializationService $seasonInit,
        private CupDrawService $cupDrawService,
    ) {}

    public function handle(CupTieResolved $event): void
    {
        $competitionId = $event->competition?->id ?? $event->cupTie->competition_id ?? null;

        if (!in_array($competitionId, SeasonInitializationService::QUALIFYING_COMPETITIONS, true)) {
            return;
        }

        $gameId = $event->game->id;
        $winnerId = (string) $event->winnerId;
        $loserId = (string) ($event->cupTie->home_team_id === $winnerId
            ? $event->cupTie->away_team_id
            : $event->cupTie->home_team_id);

        if ($competitionId === 'UCLQ') {
            $this->moveEntry($gameId, $winnerId, 'UCLQ', 'UCL');
            $this->moveEntry($gameId, $loserId, 'UCLQ', 'UELQ', 2);
            Log::info("[Qualifying] UCLQ: {$winnerId} → UCL, {$loserId} → UELQ R2 (game {$gameId})");
        } else {
            $round = (int) $event->cupTie->round_number;
            if ($round === 2) {
                // Only round-2 winners reach the Europa Cup proper (16).
                // Round-1 winners stay in UELQ — their entry already covers R2.
                $this->moveEntry($gameId, $winnerId, 'UELQ', 'UEL');
            }
            // UELQ loser is out in any round (see class docblock): drop their
            // entry so they no longer count as "qualified" in
            // strongestUnqualifiedEuropeanTeams() and future draws.
            CompetitionEntry::where('game_id', $gameId)
                ->where('competition_id', 'UELQ')
                ->where('team_id', $loserId)
                ->delete();
            Log::info("[Qualifying] UELQ R{$round}: {$winnerId} stays/→ UEL, {$loserId} out (game {$gameId})");
        }

        $this->maybeInitializeEuropeanCompetitions($event->game);
    }

    /**
     * Move a team's competition entry from one competition to another.
     * Uses updateOrInsert so re-firing (re-simulated ties) stays idempotent.
     */
    private function moveEntry(string $gameId, string $teamId, string $from, string $to, int $entryRound = 1): void
    {
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $from)
            ->where('team_id', $teamId)
            ->delete();

        CompetitionEntry::updateOrInsert(
            ['game_id' => $gameId, 'competition_id' => $to, 'team_id' => $teamId],
            ['entry_round' => $entryRound]
        );
    }

    /**
     * Once the qualifying playoffs are decided, initialize what they feed.
     *
     * - UCL: when every UCLQ tie is decided, initialize the deferred Swiss
     *   league phase (for the user's team) if it has no fixtures yet.
     * - UELQ round 2: drawn once every UCLQ tie AND every UELQ round-1 tie is
     *   decided — the 8 UCLQ losers join the 12 R1 winners and the 12 direct
     *   R2 entrants. The generic ConductNextCupRoundDraw can't gate on this
     *   cross-competition dependency, so it happens here.
     * - UEL: when every UELQ tie (R1+R2) is decided and the UEL holds its 16
     *   round-2 winners with no ties drawn yet, draw its round of 16.
     */
    private function maybeInitializeEuropeanCompetitions($game): void
    {
        $gameId = $game->id;

        $uclqUndecided = CupTie::where('game_id', $gameId)
            ->where('competition_id', 'UCLQ')
            ->where('completed', false)
            ->exists();

        if (!$uclqUndecided) {
            $this->maybeInitializeUclLeaguePhase($game);
        }

        $this->maybeDrawUelqRound2($gameId);
        $this->maybeDrawUelFirstRound($gameId);
    }

    /**
     * Initialize the deferred UWCL Swiss league phase once UCLQ is decided
     * (for the user's team), if it has no fixtures yet.
     */
    private function maybeInitializeUclLeaguePhase($game): void
    {
        $gameId = $game->id;

        $hasFixtures = GameMatch::where('game_id', $gameId)
            ->where('competition_id', 'UCL')
            ->exists();

        if ($hasFixtures) {
            return;
        }

        $this->seasonInit->initializeSwissCompetition(
            $gameId,
            (string) $game->team_id,
            'UCL',
            (string) $game->season,
            null, // auto pots by market value
        );

        Log::info("[Qualifying] UCL league phase initialized after qualifying (game {$gameId})");
    }

    /**
     * Draw UELQ round 2 once its whole field is known. needsDrawForRound()
     * already encodes the gates (every UCLQ tie decided, every UELQ round-1
     * tie decided, round 2 not drawn yet, round config present), so this is
     * a no-op unless the draw is actually due.
     */
    private function maybeDrawUelqRound2(string $gameId): void
    {
        if (!$this->cupDrawService->needsDrawForRound($gameId, 'UELQ', 2)) {
            return;
        }

        $this->cupDrawService->conductDraw($gameId, 'UELQ', 2);

        Log::info("[Qualifying] UELQ round 2 drawn (game {$gameId})");
    }

    /**
     * Draw the Europa Cup's round of 16 once UELQ is fully decided: no
     * undecided UELQ ties, the 16 round-2 winners sitting in the UEL, and
     * no UEL ties drawn yet.
     */
    private function maybeDrawUelFirstRound(string $gameId): void
    {
        $undecided = CupTie::where('game_id', $gameId)
            ->where('competition_id', 'UELQ')
            ->where('completed', false)
            ->exists();

        if ($undecided) {
            return;
        }

        // The 16 UEL entries are the decided UELQ round-2 winners; any other
        // count means qualifying hasn't produced the full field yet.
        $uelEntries = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', 'UEL')
            ->count();

        if ($uelEntries !== 16) {
            return;
        }

        if (!$this->cupDrawService->needsDrawForRound($gameId, 'UEL', 1)) {
            return;
        }

        $this->cupDrawService->conductDraw($gameId, 'UEL', 1);

        Log::info("[Qualifying] UEL round of 16 drawn (game {$gameId})");
    }
}
