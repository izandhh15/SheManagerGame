<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Season\Services\SeasonTransitionChunkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Run a time-boxed chunk of the season transition.
 *
 * Polled by the frontend loading screen. Each call does up to ~25 seconds
 * of transition work (safe under serverless HTTP timeouts) and persists a
 * checkpoint, so repeated polls advance the transition to completion even
 * if individual requests die.
 */
class AdvanceSeasonTransition
{
    public function __construct(
        private readonly SeasonTransitionChunkService $chunkService,
    ) {}

    public function __invoke(string $gameId): JsonResponse
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Only the game owner may advance their own transition.
        if ($game->user_id !== auth()->id()) {
            abort(403);
        }

        try {
            $result = $this->chunkService->runChunk($game, 25.0);
        } catch (\Throwable $e) {
            Log::error('AdvanceSeasonTransition failed', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'done' => false,
                'error' => $e->getMessage(),
                'step' => $game->season_transition_step,
                'totalSteps' => null,
            ], 500);
        }

        $progress = null;
        if (!$result['done'] && $result['step'] !== null) {
            $progress = min(100, (int) round(($result['step'] + 1) / $result['totalSteps'] * 100));
        }

        return response()->json([
            'done' => $result['done'],
            'step' => $result['step'],
            'totalSteps' => $result['totalSteps'],
            'progress' => $progress ?? ($result['done'] ? 100 : 0),
        ]);
    }
}
