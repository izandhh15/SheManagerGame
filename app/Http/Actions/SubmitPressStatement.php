<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Media\Services\SocialMediaService;
use Illuminate\Http\Request;

class SubmitPressStatement
{
    public function __construct(
        private readonly SocialMediaService $socialMedia,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId)
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::findOrFail($matchId);

        if ($match->game_id !== $game->id || (int) $game->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'statement_key' => 'required|string|in:praise_star,praise_team,criticize_flop,criticize_team,blame_referee,defend_tactics',
            'player_id' => 'nullable|string',
        ]);

        // One statement per match.
        $exists = \App\Models\PressStatement::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->exists();

        if (! $exists) {
            $this->socialMedia->makeStatement(
                $game,
                $match,
                $validated['statement_key'],
                $validated['player_id'] ?? null,
            );
        }

        // If sacked, the board confidence collapsed — redirect to the feed
        // where the sacking posts are visible.
        return redirect()->route('game.social', $game->id)
            ->with('press_done', true);
    }
}
