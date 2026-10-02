<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Media\Services\PressConferenceService;

class ShowPreMatchPress
{
    public function __construct(
        private readonly PressConferenceService $press,
    ) {}

    public function __invoke(string $gameId, string $matchId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        $match = GameMatch::with(['homeTeam', 'awayTeam', 'competition'])->findOrFail($matchId);

        if ($match->game_id !== $game->id) {
            abort(404);
        }

        // Press conferences only happen before big matches.
        if (! $this->press->isBigMatch($game, $match)) {
            return redirect()->route('game.lineup', $game->id);
        }

        $record = $this->press->findRecord($game, $match);

        return view('pre-match-press', [
            'game' => $game,
            'match' => $match,
            'reasons' => $this->press->bigMatchReasons($game, $match),
            'questions' => $this->press->questions($game, $match),
            'record' => $record,
        ]);
    }
}
