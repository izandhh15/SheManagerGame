<?php

namespace App\Http\Views;

use App\Models\Game;

/**
 * Next-gen game hub (fase 1, rama next-gen-menus).
 *
 * A menu-only page: it loads the game (ownership is enforced by the
 * `game.owner` middleware on the route group, like every other game
 * route) and renders game-hub-nextgen with the new shell. It does NOT
 * change any game state, data or logic — every card links to an existing
 * route whose own view class still runs with the old layout.
 */
class ShowGameMenu
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        return view('game-hub-nextgen', [
            'game' => $game,
            'nextMatch' => $game->next_match,
        ]);
    }
}
