<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Season\Services\PreseasonInvitationService;
use Illuminate\Http\Request;

class DeclinePreseasonInvitation
{
    public function __construct(
        private readonly PreseasonInvitationService $invitationService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (! $game->needsPreseasonOpponentSelection()) {
            return redirect()->route('show-game', $gameId);
        }

        $validated = $request->validate([
            'invitation_id' => ['required', 'string'],
        ]);

        $this->invitationService->decline($game, $validated['invitation_id']);

        return redirect()->route('game.preseason-setup', $gameId);
    }
}
