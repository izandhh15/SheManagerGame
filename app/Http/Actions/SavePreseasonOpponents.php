<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\PreseasonInvitation;
use App\Modules\Season\Services\PreseasonOpponentService;
use Illuminate\Http\Request;

class SavePreseasonOpponents
{
    public function __construct(
        private readonly PreseasonOpponentService $opponentService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (! $game->needsPreseasonOpponentSelection()) {
            return redirect()->route('show-game', $gameId);
        }

        $validated = $request->validate([
            'slots' => ['array'],
            'slots.*.team_id' => ['nullable', 'string'],
            'slots.*.is_home' => ['nullable', 'boolean'],
            'slots.*.trophy_name' => ['nullable', 'string', 'max:100'],
            'slots.*.stadium_name' => ['nullable', 'string', 'max:100'],
        ]);

        // Reshape the per-slot form input into the service's selection list,
        // dropping slots the player left empty. The service does the real
        // validation (valid pool members, unique slots/teams).
        //
        // Accepted AI invitations go FIRST so they win if the user also
        // picked a manual opponent for the same slot (sanitizeSelections
        // keeps the first fixture per slot). The setup screen locks those
        // slots visually, but this keeps the merge safe even if the form
        // is tampered with.
        $selections = [];
        $acceptedInvitations = PreseasonInvitation::where('game_id', $game->id)
            ->where('status', PreseasonInvitation::STATUS_ACCEPTED)
            ->get();

        foreach ($acceptedInvitations as $invitation) {
            $selections[] = [
                'slot' => $invitation->slot,
                'team_id' => $invitation->inviting_team_id,
                // Invited matches are played at the inviter's ground (their trophy, their stadium).
                'is_home' => false,
                'trophy_name' => $invitation->trophy_name,
                'stadium_name' => $invitation->stadium_name,
            ];
        }

        foreach ($validated['slots'] ?? [] as $slot => $input) {
            if (empty($input['team_id'])) {
                continue;
            }

            $selections[] = [
                'slot' => (int) $slot,
                'team_id' => $input['team_id'],
                'is_home' => filter_var($input['is_home'] ?? true, FILTER_VALIDATE_BOOL),
                'trophy_name' => $input['trophy_name'] ?? null,
                'stadium_name' => $input['stadium_name'] ?? null,
            ];
        }

        $this->opponentService->confirmSelections($game, $selections);

        return redirect()->route('show-game', $gameId)
            ->with('info', __('game.pre_season_ready'));
    }
}
