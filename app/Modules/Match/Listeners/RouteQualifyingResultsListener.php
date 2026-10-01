<?php

namespace App\Modules\Match\Listeners;

use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Season\Services\SeasonInitializationService;
use Illuminate\Support\Facades\Log;

/**
 * Routes UEFA qualifying playoff results into the league phases.
 *
 * - UCLQ winner → UCL league phase; UCLQ loser → drops to the Europa Cup
 *   (the real UWCL → UWEC drop-down: e.g. Juventus out in qualifying
 *   plays the Europa Cup).
 * - UELQ winner → Europa Cup league phase; UELQ loser is out.
 *
 * When every qualifying tie is decided, initializes the deferred UCL/UEL
 * Swiss league phases (they wait for these slots at season setup).
 */
class RouteQualifyingResultsListener
{
    public function __construct(
        private SeasonInitializationService $seasonInit,
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
            $this->moveEntry($gameId, $loserId, 'UCLQ', 'UEL');
            Log::info("[Qualifying] UCLQ: {$winnerId} → UCL, {$loserId} → UEL (game {$gameId})");
        } else {
            $this->moveEntry($gameId, $winnerId, 'UELQ', 'UEL');
            Log::info("[Qualifying] UELQ: {$winnerId} → UEL (game {$gameId})");
        }

        $this->maybeInitializeEuropeanLeaguePhases($event->game);
    }

    /**
     * Move a team's competition entry from one competition to another.
     * Uses updateOrInsert so re-firing (re-simulated ties) stays idempotent.
     */
    private function moveEntry(string $gameId, string $teamId, string $from, string $to): void
    {
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $from)
            ->where('team_id', $teamId)
            ->delete();

        CompetitionEntry::updateOrInsert(
            ['game_id' => $gameId, 'competition_id' => $to, 'team_id' => $teamId],
            ['entry_round' => 1]
        );
    }

    /**
     * Once every UCLQ and UELQ tie has a winner, the league phases can be
     * drawn. Initializes each (for the user's team) if it has no fixtures yet.
     */
    private function maybeInitializeEuropeanLeaguePhases($game): void
    {
        $gameId = $game->id;

        $undecided = CupTie::where('game_id', $gameId)
            ->whereIn('competition_id', SeasonInitializationService::QUALIFYING_COMPETITIONS)
            ->where('completed', false)
            ->exists();

        if ($undecided) {
            return;
        }

        foreach (['UCL', 'UEL'] as $competitionId) {
            $hasFixtures = \App\Models\GameMatch::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->exists();

            if ($hasFixtures) {
                continue;
            }

            $this->seasonInit->initializeSwissCompetition(
                $gameId,
                (string) $game->team_id,
                $competitionId,
                (string) $game->season,
                null, // auto pots by market value
            );

            Log::info("[Qualifying] {$competitionId} league phase initialized after qualifying (game {$gameId})");
        }
    }
}
