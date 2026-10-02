<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Media\Services\PressConferenceService;
use Illuminate\Http\Request;

class SubmitPreMatchPress
{
    public function __construct(
        private readonly PressConferenceService $press,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId)
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::with(['homeTeam', 'awayTeam', 'competition'])->findOrFail($matchId);

        if ($match->game_id !== $game->id || (int) $game->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $questions = $this->press->questions($game, $match);

        // Build per-question validation so tampered keys fail with a 422.
        $rules = ['answers' => 'required|array'];
        foreach ($questions as $question) {
            $rules['answers.'.$question['key']] = 'required|in:'.implode(',', array_column($question['answers'], 'key'));
        }

        $validated = $request->validate($rules);

        // Idempotent: answering twice doesn't double-apply the effects.
        $this->press->answer($game, $match, $validated['answers']);

        return redirect()->route('game.lineup', $game->id)
            ->with('press_done', true);
    }
}
