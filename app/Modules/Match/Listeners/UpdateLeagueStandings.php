<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Competition\Services\StandingsCalculator;
use App\Models\GameMatch;

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
        // SIN transacción ni lock (25-10-2026, Neon pooler): la transacción
        // con lock abortaba en silencio, el listener lanzaba 25P02, eso
        // convertía /finalize-match en 500 tras cada partido Y dejaba la
        // clasificación congelada en 0 puntos. Claim atómico primero
        // (solo una petición marca standings_applied); si aplicar falla,
        // se revierte la marca para que el próximo finalize lo reintente.
        $claimed = GameMatch::where('id', $match->id)
            ->where('standings_applied', false)
            ->update(['standings_applied' => true]);

        if (! $claimed) {
            return;
        }

        try {
            $this->standingsCalculator->updateAfterMatch(
                gameId: $event->game->id,
                competitionId: $match->competition_id,
                homeTeamId: $match->home_team_id,
                awayTeamId: $match->away_team_id,
                homeScore: $match->home_score,
                awayScore: $match->away_score,
            );

            $this->standingsCalculator->recalculatePositions($event->game->id, $match->competition_id, updatePrevPosition: false);
        } catch (\Throwable $e) {
            GameMatch::where('id', $match->id)->update(['standings_applied' => false]);

            throw $e;
        }
    }
}
