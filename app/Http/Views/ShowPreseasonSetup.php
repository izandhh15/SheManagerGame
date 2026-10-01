<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Season\Services\PreseasonOpponentService;
use App\Modules\Season\Services\PreseasonInvitationService;

class ShowPreseasonSetup
{
    public function __construct(
        private readonly PreseasonOpponentService $opponentService,
        private readonly PreseasonInvitationService $invitationService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Still building the season — let show-game render the loading screen.
        if (! $game->isSetupComplete() || $game->isTransitioningSeason()) {
            return redirect()->route('show-game', $gameId);
        }

        // Selection already done (or not applicable) — back to the dashboard.
        if (! $game->needsPreseasonOpponentSelection()) {
            return redirect()->route('show-game', $gameId);
        }

        // The machine invites you: generate AI invitations (idempotent).
        $this->invitationService->generateFor($game);

        $teams = $this->opponentService->candidateTeamsGroupedByCountry($game);
        $slots = $this->opponentService->fixtureSlots($game);
        $invitations = $this->invitationService->pendingFor($game);

        return view('preseason-setup', [
            'game' => $game,
            'teams' => $teams,
            'slots' => $slots,
            'invitations' => $invitations,
        ]);
    }
}
