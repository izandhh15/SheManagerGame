<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameNotification;
use Illuminate\Http\Request;

/**
 * Shows a pending government venue offer (F5).
 */
class ShowGovernmentVenue
{
    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $offer = GameNotification::where('game_id', $game->id)
            ->ofType(GameNotification::TYPE_GOVERNMENT_VENUE_OFFER)
            ->whereNull('read_at')
            ->latest('game_date')
            ->first();

        return view('government-venue', [
            'game' => $game,
            'offer' => $offer,
        ]);
    }
}
