<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Stadium\Services\MatchdayPricingService;
use Illuminate\Http\Request;

/**
 * Save the manager's matchday prices (club mode): single ticket, official
 * shirt, merchandising and stadium bar drink. They shape both the revenue
 * booked per home match and the attendance demand curve.
 */
class SaveMatchdayPricing
{
    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $validated = $request->validate([
            'ticket_price' => ['required', 'integer', 'min:' . MatchdayPricingService::MIN_TICKET, 'max:' . MatchdayPricingService::MAX_TICKET],
            'shirt_price' => ['required', 'integer', 'min:' . MatchdayPricingService::MIN_SHIRT, 'max:' . MatchdayPricingService::MAX_SHIRT],
            'merch_price' => ['required', 'integer', 'min:' . MatchdayPricingService::MIN_MERCH, 'max:' . MatchdayPricingService::MAX_MERCH],
            'bar_price' => ['required', 'integer', 'min:' . MatchdayPricingService::MIN_BAR, 'max:' . MatchdayPricingService::MAX_BAR],
        ]);

        $game->update($validated);

        return redirect()
            ->route('game.club.matchday', ['gameId' => $gameId])
            ->with('success', __('game.matchday_pricing_saved'));
    }
}
