<?php

namespace App\Http\Actions;

use App\Modules\Season\Services\GameDeletionService;
use App\Models\Game;
use Illuminate\Http\Request;

class DeleteGame
{
    public function __invoke(Request $request, string $gameId, GameDeletionService $service)
    {
        $game = Game::findOrFail($gameId);

        if ((int) $game->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        // Dual mode: the two halves of a pair are bookkeeping for the same
        // manager career, so deleting one side deletes the pair. A lone
        // secondary would dangle (no primary to navigate back to, and it
        // never counts against the game limit). The partner is resolved
        // before flagging this game as deleting; GameDeletionService only
        // marks + queues the async cleanup per game.
        $partner = $game->dualPartner();

        $service->delete($game);

        if ($partner && ! $partner->isDeleting()) {
            $service->delete($partner);
        }

        return redirect()->route('dashboard')->with('success', __('messages.game_deleted'));
    }
}
