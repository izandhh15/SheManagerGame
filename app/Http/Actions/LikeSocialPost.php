<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\SocialPost;
use Illuminate\Http\Request;

/**
 * Let the manager "like" a post on the fake social network.
 * One like per post per session (no accounts on a fake network).
 */
class LikeSocialPost
{
    public function __invoke(Request $request, string $gameId, string $postId)
    {
        $game = Game::findOrFail($gameId);

        if ((int) $game->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $post = SocialPost::where('game_id', $game->id)->findOrFail($postId);

        $sessionKey = "liked_posts:{$game->id}";
        $liked = $request->session()->get($sessionKey, []);

        if (! in_array($postId, $liked, true)) {
            $post->increment('likes');
            $request->session()->put($sessionKey, [...$liked, $postId]);
        }

        return redirect()->route('game.social', $game->id);
    }
}
