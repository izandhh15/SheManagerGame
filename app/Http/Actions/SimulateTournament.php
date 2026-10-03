<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\TournamentSummary;
use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Modules\Season\Listeners\DetectTournamentEnded;
use Illuminate\Http\JsonResponse;

/**
 * Chunked tournament simulation endpoint.
 *
 * The old version ran up to 500 orchestrator advance() calls inside a
 * single GET request — the same class of serverless HTTP timeout that
 * forced the chunked season transition in 0.3.67 — and a GET with
 * mutating effects is CSRF-able via prefetch/<img>.
 *
 * This endpoint runs advance() in a ~20s time budget and returns JSON:
 *   {done: false, advanced: <n>}          → client re-POSTs to continue
 *   {done: true, redirect: <url>}         → client navigates there
 *   {done: true, redirect: <url>, warning}→ blocked: client navigates
 *
 * The game row itself is the checkpoint: every advance() runs in its own
 * transaction (MatchdayOrchestrator locks the game row), so a re-POST
 * resumes exactly where the previous chunk stopped.
 *
 * CALLER CONTRACT (for the blade/JS caller): POST with a CSRF token,
 * poll until done === true, then window.location = data.redirect.
 */
class SimulateTournament
{
    /**
     * Per-request time budget — must stay well under serverless HTTP
     * timeouts (25s was chosen for the season-transition chunks).
     */
    private const CHUNK_SECONDS = 20.0;

    /** Safety cap so a single chunk can never run away. */
    private const MAX_ADVANCES_PER_CHUNK = 500;

    public function __construct(
        private readonly MatchdayOrchestrator $orchestrator,
        private readonly DetectTournamentEnded $tournamentEndDetector,
    ) {}

    public function __invoke(string $gameId): JsonResponse
    {
        $game = Game::findOrFail($gameId);

        if (! $game->isTournamentMode()) {
            return $this->finished(route('show-game', $gameId));
        }

        $deadline = microtime(true) + self::CHUNK_SECONDS;
        $advanced = 0;
        $tournamentSimulated = false;

        for ($i = 0; $i < self::MAX_ADVANCES_PER_CHUNK; $i++) {
            $result = $this->orchestrator->advance($game);

            if ($result->type === 'live_match') {
                return $this->finished(route('game.live-match', [
                    'gameId' => $game->id,
                    'matchId' => $result->matchId,
                ]));
            }

            if ($result->type === 'blocked') {
                // Transient block — hand back to the game view instead of
                // ending the tournament.
                return $this->finished(
                    route('show-game', $game->id),
                    ['warning' => __('messages.action_required')]
                );
            }

            if (in_array($result->type, ['done', 'season_complete'], true)) {
                $tournamentSimulated = true;
                break;
            }

            $game->refresh()->setRelations([]);
            $advanced++;

            if (microtime(true) >= $deadline) {
                break; // Budget spent — the client re-POSTs to continue.
            }
        }

        if (! $tournamentSimulated) {
            return response()->json(['done' => false, 'advanced' => $advanced]);
        }

        // Finalization (snapshot, soft-delete, activation) happens via the
        // TournamentEnded listener chain fired from DetectTournamentEnded.
        // MatchFinalizationService invokes the detector for the user's matches;
        // call it here too so AI-only completions (e.g. an eliminated user
        // fast-forwarding through remaining knockouts) still end the tournament.
        $this->tournamentEndDetector->detect($game->refresh()->setRelations([]));

        $summary = TournamentSummary::where('original_game_id', $game->id)->first();

        return $this->finished(
            $summary
                ? route('tournament-summary.show', $summary->id)
                : route('show-game', $game->id)
        );
    }

    private function finished(string $redirect, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(
            ['done' => true, 'redirect' => $redirect],
            $extra
        ));
    }
}
