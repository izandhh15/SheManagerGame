<?php

namespace App\Http\Actions;

use App\Events\SeasonCompleted;
use App\Models\TournamentSummary;
use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Season\Listeners\DetectTournamentEnded;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\Request;

class FinalizeMatch
{
    public function __construct(
        private readonly MatchFinalizationService $finalizationService,
        private readonly MatchdayOrchestrator $orchestrator,
        private readonly DetectTournamentEnded $tournamentEndDetector,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        // SIN transacción ni lockForUpdate (25-10-2026, Neon pooler):
        // la transacción con la fila bloqueada abortaba en silencio y
        // /finalize-match daba 500 tras CADA partido (y como el fallo
        // llegaba en el listener de clasificaciones, la clasificación
        // quedaba congelada en 0 puntos toda la temporada). El
        // anti-doble-clic ahora es un claim atómico: limpiar
        // pending_finalization_match_id solo donde todavía vale el
        // matchId; si 0 filas cambian, otra petición ya lo reclamó.
        $game = Game::find($gameId);
        $finalization = null;

        if ($game) {
            $matchId = $game->pending_finalization_match_id;

            if ($matchId) {
                $match = GameMatch::find($matchId);

                if (! $match || ! $match->played) {
                    $game->update(['pending_finalization_match_id' => null]);
                } else {
                    $claimed = Game::where('id', $game->id)
                        ->where('pending_finalization_match_id', $matchId)
                        ->update(['pending_finalization_match_id' => null]);

                    if ($claimed) {
                        $finalization = $this->finalizationService->finalize($match, $game->fresh());
                    }
                }
            }
        }

        if (! $game) {
            return redirect()->route('show-game', $gameId);
        }

        if ($finalization) {
            $this->finalizationService->dispatchPostFinalizeEffects($finalization);
        }

        // Fire SeasonCompleted if no unplayed matches remain after finalization.
        // This covers the case where the player's match is the last match of the
        // season (e.g. promotion playoff final) — ShowGame would redirect straight
        // to season-end, bypassing AdvanceMatchday which normally fires this event.
        // Tournament mode is handled by the TournamentEnded event chain dispatched
        // from DetectTournamentEnded, which is invoked by MatchFinalizationService
        // after beforeMatches has had a chance to generate the next knockout round.
        $hasRemainingMatches = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->exists();

        if (! $hasRemainingMatches) {
            if ($game->isTournamentMode()) {
                return $this->redirectToTournamentSummary($game);
            }

            event(new SeasonCompleted($game));
        }

        // Tournament auto-simulation: advance all remaining matches and go to summary
        if ($request->has('tournament_end') && $game->isTournamentMode()) {
            return $this->simulateRemainingAndRedirect($game);
        }

        return redirect()->route('show-game', $gameId);
    }

    private function simulateRemainingAndRedirect(Game $game)
    {
        $game->refresh()->setRelations([]);

        for ($i = 0; $i < 500; $i++) {
            $result = $this->orchestrator->advance($game);

            if ($result->type === 'live_match') {
                return redirect()->route('game.live-match', [
                    'gameId' => $game->id,
                    'matchId' => $result->matchId,
                ]);
            }

            if ($result->type === 'blocked') {
                // Transient block — redirect back to game view instead of ending tournament
                return redirect()->route('show-game', $game->id)
                    ->with('warning', __('messages.action_required'));
            }

            if (in_array($result->type, ['done', 'season_complete'])) {
                break;
            }

            $game->refresh()->setRelations([]);
        }

        // AI-only batches don't flow through MatchFinalizationService::finalize,
        // so DetectTournamentEnded isn't invoked during auto-simulation. Run it
        // here to cover the eliminated-user fast-forward path where every
        // remaining match is AI-only.
        $this->tournamentEndDetector->detect($game->refresh()->setRelations([]));

        return $this->redirectToTournamentSummary($game);
    }

    private function redirectToTournamentSummary(Game $game)
    {
        $summary = TournamentSummary::where('original_game_id', $game->id)->first();

        if (! $summary) {
            // Should not happen: the TournamentEnded listener chain persists the
            // summary synchronously before this method runs. Fall back to the game
            // view rather than hard-failing.
            return redirect()->route('show-game', $game->id);
        }

        return redirect()->route('tournament-summary.show', $summary->id);
    }
}
