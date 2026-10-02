<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\SocialPost;
use App\Modules\Media\Services\SocialMediaService;
use Illuminate\Http\Request;

class ReplyToHater
{
    public function __construct(
        private readonly SocialMediaService $socialMedia,
    ) {}

    public function __invoke(Request $request, string $gameId, string $postId)
    {
        $game = Game::findOrFail($gameId);

        if ((int) $game->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'reply_key' => 'required|string|in:'.implode(',', array_keys(SocialMediaService::HATER_REPLIES)),
        ]);

        $post = SocialPost::where('game_id', $game->id)->findOrFail($postId);

        // Only unanswered hater posts can be replied to.
        if ($post->sentiment >= 0 || $post->manager_reply_key !== null) {
            return redirect()->route('game.social', $game->id);
        }

        $this->socialMedia->replyToHater($game, $post, $validated['reply_key']);

        return redirect()->route('game.social', $game->id)
            ->with('reply_done', true);
    }
}
