<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameMatch;
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

        // Upcoming home matches with a confirmed venue (for the
        // pre-written "announce venue" template).
        $upcomingHomeMatches = GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->where(fn ($q) => $q
                ->whereNotNull('stadium_name')
                ->orWhereNotNull('neutral_venue_name'))
            ->with('awayTeam:id,name')
            ->orderBy('scheduled_date')
            ->limit(5)
            ->get(['id', 'scheduled_date', 'stadium_name', 'neutral_venue_name', 'away_team_id']);

        return view('club-social', [
            'game' => $game,
            'posts' => $this->clubSocial->feed($game),
            'squad' => $squad,
            'recentSignings' => $this->recentTransfers($game, 'in'),
            'recentSales' => $this->recentTransfers($game, 'out'),
            'clubHandle' => $this->clubSocial->clubHandle($game),
            'followers' => $this->clubSocial->followers($game),
            'hype' => $this->clubSocial->hype($game),
            'clubLang' => $this->clubSocial->clubLang($game),
            'nextHome' => $this->clubSocial->nextHomeMatch($game),
            'nextFriendly' => $this->clubSocial->nextFriendlyMatch($game),
            'upcomingHomeMatches' => $upcomingHomeMatches,
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
