<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Stadium\Services\NationalVenueOrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizeNationalVenue
{
    public function __construct(
        private readonly NationalVenueOrganizationService $orgService,
    ) {}

    public function __invoke(Request $request, string $gameId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        if (! $game->isTournamentMode()) {
            abort(404);
        }

        $validated = $request->validate([
            'match_id' => ['required', 'string'],
            'venue_type' => ['required', 'in:national,club,mens,neutral'],
            'stadium' => ['nullable', 'string'],
            'club_team_id' => ['nullable', 'string'],
            'mens_stadium' => ['nullable', 'string'],
            // The manager offers whatever they want, within the budget.
            'offer' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        $match = GameMatch::where('game_id', $gameId)->find($validated['match_id']);

        if (! $match) {
            return redirect()->back()->with('error', __('game.venue_org_invalid_match'));
        }

        $result = $this->orgService->organize(
            $game,
            $match,
            $validated['venue_type'],
            [
                'stadium' => $validated['stadium'] ?? null,
                'club_team_id' => $validated['club_team_id'] ?? null,
                'mens_stadium' => $validated['mens_stadium'] ?? null,
            ],
            (int) ($validated['offer'] ?? 0),
        );

        return redirect()
            ->route('game.national-venues', $gameId)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
