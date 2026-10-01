<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Media\Services\SocialMediaService;

class ShowPressConference
{
    public function __construct(
        private readonly SocialMediaService $socialMedia,
    ) {}

    public function __invoke(string $gameId, string $matchId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        $match = GameMatch::with(['homeTeam', 'awayTeam'])->findOrFail($matchId);

        if ($match->game_id !== $game->id) {
            abort(404);
        }

        // Already gave a statement for this match?
        $alreadyDone = \App\Models\PressStatement::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->exists();

        $options = $alreadyDone ? [] : $this->socialMedia->pressOptions($game, $match);

        return view('press-conference', [
            'game' => $game,
            'match' => $match,
            'options' => $options,
            'alreadyDone' => $alreadyDone,
        ]);
    }
}
