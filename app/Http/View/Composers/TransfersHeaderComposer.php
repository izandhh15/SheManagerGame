<?php

namespace App\Http\View\Composers;

use App\Models\Game;
use App\Models\TransferOffer;
use Illuminate\View\View;

/**
 * Provides the shared transfers header with the budget committed to active
 * bids. The header is included by every transfers tab; the committed-budget
 * aggregate used to be queried inline in the partial. It is a pure function
 * of the game, so it is memoized per game for the request.
 */
class TransfersHeaderComposer
{
    /** @var array<string, int> game_id => committed budget */
    private static array $memo = [];

    public function compose(View $view): void
    {
        $game = $view->getData()['game'] ?? null;

        if (! $game instanceof Game) {
            $view->with('committedBudget', 0);

            return;
        }

        self::$memo[$game->id] ??= TransferOffer::committedBudget($game->id);

        $view->with('committedBudget', self::$memo[$game->id]);
    }
}
