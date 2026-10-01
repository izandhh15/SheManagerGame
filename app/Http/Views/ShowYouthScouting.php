<?php

namespace App\Http\Views;

use App\Models\AcademyPlayer;
use App\Models\Game;

class ShowYouthScouting
{
    public function __invoke(string $gameId)
    {
        $game = Game::with(['team'])->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        // Academy players from OTHER teams (rivals' youth systems).
        // The user can scout them whether managing the first team or the B team.
        $prospects = AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', '!=', $game->team_id)
            ->with('team')
            ->orderByDesc('potential')
            ->limit(50)
            ->get();

        return view('youth-scouting', [
            'game' => $game,
            'prospects' => $prospects,
        ]);
    }
}
