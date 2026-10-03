<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Competition\Services\StandingsCalculator;
use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;

class UpdateLeagueStandings
{
    public function __construct(
        private readonly StandingsCalculator $standingsCalculator,
    ) {}

    public function handle(MatchFinalized $event): void
    {
        $match = $event->match;
        $competition = $event->competition;
        $isCupTie = $match->cup_tie_id !== null;

        if (! $competition?->isLeague() || $isCupTie) {
            return;
        }

        // Idempotency guard: skip if standings were already applied for this match
        // (prevents double-counting from concurrent finalization or safety net re-entry).
        // The check-then-set runs inside a transaction with a row lock on the
        // match: two concurrent finalizations serialize here and the second
        // one sees standings_applied = true instead of double-applying.
        DB::transaction(function () use ($event, $match) {
            $locked = GameMatch::where('id', $match->id)->lockForUpdate()->first();

            if (!$locked || $locked->standings_applied) {
                return;
            }

            $this->standingsCalculator->updateAfterMatch(
                gameId: $event->game->id,
                competitionId: $match->competition_id,
                homeTeamId: $match->home_team_id,
                awayTeamId: $match->away_team_id,
                homeScore: $match->home_score,
                awayScore: $match->away_score,
            );

            $this->standingsCalculator->recalculatePositions($event->game->id, $match->competition_id, updatePrevPosition: false);

            $locked->update(['standings_applied' => true]);
        });
    }
}
