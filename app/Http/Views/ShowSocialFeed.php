<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\SocialPost;

class ShowSocialFeed
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $posts = SocialPost::where('game_id', $game->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $negativeCount = SocialPost::where('game_id', $game->id)
            ->where('sentiment', -1)
            ->count();
        $positiveCount = SocialPost::where('game_id', $game->id)
            ->where('sentiment', 1)
            ->count();

        return view('social-feed', [
            'game' => $game,
            'posts' => $posts,
            'negativeCount' => $negativeCount,
            'positiveCount' => $positiveCount,
            'boardConfidence' => $game->board_confidence ?? 70,
        ]);
    }
}
