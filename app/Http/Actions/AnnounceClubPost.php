<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Media\Services\ClubSocialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnounceClubPost
{
    public function __construct(
        private readonly ClubSocialService $clubSocial,
    ) {}

    public function __invoke(Request $request, string $gameId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        if ($game->isTournamentMode()) {
            abort(404);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', ClubSocialService::TYPES)],
            'player_id' => ['nullable', 'string'],
            'match_id' => ['nullable', 'string'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
        ]);

        $result = $this->clubSocial->announce(
            $game,
            $validated['type'],
            $validated['player_id'] ?? null,
            [
                'match_id' => $validated['match_id'] ?? null,
                'weeks' => $validated['weeks'] ?? null,
            ],
        );

        return redirect()->route('game.club-social', $gameId)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
