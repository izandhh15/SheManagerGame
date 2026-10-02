<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Media\Services\ClubSocialService;

class ShowClubSocial
{
    public function __construct(
        private readonly ClubSocialService $clubSocial,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        if ($game->isTournamentMode()) {
            return redirect()->route('show-game', $gameId);
        }

        $squad = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->orderByDesc('overall_score')
            ->get(['id', 'name', 'position', 'overall_score']);

        return view('club-social', [
            'game' => $game,
            'posts' => $this->clubSocial->feed($game),
            'squad' => $squad,
            'clubHandle' => $this->clubSocial->clubHandle($game),
            'followers' => $this->clubSocial->followers($game),
            'hype' => $this->clubSocial->hype($game),
        ]);
    }
}
