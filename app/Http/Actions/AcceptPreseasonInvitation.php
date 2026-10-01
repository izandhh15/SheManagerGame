<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Season\Services\PreseasonInvitationService;
use Illuminate\Http\Request;

class AcceptPreseasonInvitation
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

        $slot = $this->invitationService->accept($game, $validated['invitation_id']);

        if ($slot === null) {
            return redirect()->route('game.preseason-setup', $gameId)
                ->with('error', __('game.preseason_invitation_expired'));
        }

        return redirect()->route('game.preseason-setup', $gameId)
            ->with('info', __('game.preseason_invitation_accepted'));
    }
}
