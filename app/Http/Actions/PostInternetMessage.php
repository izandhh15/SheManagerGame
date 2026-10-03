<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\SocialPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The manager posts on the Internet feed. Rate-limited: 5 posts/day per game.
 */
class PostInternetMessage
{
    public const DAILY_LIMIT = 5;
    public const MAX_LENGTH = 280;

    public function __invoke(Request $request, string $gameId): RedirectResponse
    {
        $game = Game::with('team')->findOrFail($gameId);

        if ((int) $game->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:' . self::MAX_LENGTH],
        ]);

        $postedToday = SocialPost::where('game_id', $game->id)
            ->where('context', 'manager_post')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($postedToday >= self::DAILY_LIMIT) {
            return redirect()->back()->with('error', __('game.internet_limit_reached', ['limit' => self::DAILY_LIMIT]));
        }

        $user = $request->user();
        $name = $user->username ?? $user->name ?? 'Míster';
        $handle = '@' . strtolower(preg_replace('/[^a-z0-9]/i', '', $name));

        SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $name,
            'author_handle' => $handle,
            'text' => trim($validated['text']),
            'sentiment' => 0,
            'likes' => rand(50, 800),
            'context' => 'manager_post',
        ]);

        return redirect()->route('game.internet', ['gameId' => $gameId, 'tab' => 'mister'])
            ->with('success', __('game.internet_posted'));
    }
}
