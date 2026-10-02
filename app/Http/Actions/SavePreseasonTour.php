<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Season\Services\PreseasonTourService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavePreseasonTour
{
    public function __construct(
        private readonly PreseasonTourService $tours,
    ) {}

    public function __invoke(Request $request, string $gameId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        if ($game->isTournamentMode()) {
            abort(404);
        }

        $validated = $request->validate([
            'destination' => ['required', 'string', 'max:50'],
        ]);

        $result = $this->tours->organize($game, $validated['destination']);

        return redirect()->route('game.preseason-setup', $gameId)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
