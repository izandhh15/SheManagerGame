<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameNotification;
use Illuminate\Http\Request;

/**
 * Shows a pending government-paid friendly offer (F4): the government,
 * the opponent, the fee — with Accept / Reject buttons.
 */
class ShowGovernmentFriendly
{
    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $offer = GameNotification::where('game_id', $game->id)
            ->ofType(GameNotification::TYPE_GOVERNMENT_FRIENDLY_OFFER)
            ->whereNull('read_at')
            ->latest('game_date')
            ->first();

        return view('government-friendly', [
            'game' => $game,
            'offer' => $offer,
        ]);
    }
}
