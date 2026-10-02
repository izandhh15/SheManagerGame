<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
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
            'recentSignings' => $this->recentTransfers($game, 'in'),
            'recentSales' => $this->recentTransfers($game, 'out'),
            'clubHandle' => $this->clubSocial->clubHandle($game),
            'followers' => $this->clubSocial->followers($game),
            'hype' => $this->clubSocial->hype($game),
        ]);
    }

    /**
     * Players signed ('in') or sold ('out') this season — the only ones
     * that make sense in a signing/sale announcement. Resolved through the
     * transfer ledger; a sold player's GamePlayer row survives the move
     * (it just points at the new team), so names stay available.
     *
     * @return \Illuminate\Support\Collection<int, GamePlayer>
     */
    private function recentTransfers(Game $game, string $direction)
    {
        $query = GameTransfer::where('game_id', $game->id)
            ->where('season', (string) $game->season);

        if ($direction === 'in') {
            $query->where('to_team_id', $game->team_id);
        } else {
            $query->where('from_team_id', $game->team_id);
        }

        return $query->with('gamePlayer:id,name,overall_score')
            ->get()
            ->map(fn (GameTransfer $t) => $t->gamePlayer)
            ->filter()
            ->unique('id')
            ->values();
    }
}
