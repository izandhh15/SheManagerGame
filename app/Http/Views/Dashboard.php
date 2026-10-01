<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class Dashboard
{
    public function __construct()
    {
    }

    public function __invoke(Request $request)
    {
        $games = Game::with(['team', 'competition'])->where('user_id', $request->user()->id)->whereNull('deleting_at')->get();

        if (! $games->count()) {
            return redirect()->route('select-team');
        }

        $maxGames = 3;

        // Same limit semantics as InitGame/InitDualGame/SelectTeam: only
        // primary saves count against the 3-game limit. Dual-mode
        // secondaries (linked_game_id not null) ride along with their club
        // half and don't consume a slot — otherwise the dashboard would
        // show "4 de 3" after creating a dual career.
        $primaryCount = $games->count();
        if (Schema::hasColumn('games', 'linked_game_id')) {
            $primaryCount = $games->whereNull('linked_game_id')->count();
        }

        // Affiliate-mode CTA: surfaced on the dashboard so the mode isn't
        // buried in the new-game screen. Only when the user has a free
        // slot and at least one club has a playable filial.
        $showAffiliateCta = $primaryCount < $maxGames && Team::where('type', '!=', 'national')
            ->where('is_placeholder', false)
            ->whereNull('parent_team_id')
            ->whereHas('reserveTeam', fn ($q) => $q->where('is_placeholder', false))
            ->exists();

        return view('dashboard', [
            'user' => $request->user(),
            'games' => $games,
            'canCreateGame' => $primaryCount < $maxGames,
            // Only users who already have a career from an older data season
            // need telling that saves keep the squads they started with.
            'hasLegacySaves' => $games->contains(
                fn (Game $game) => ! $game->isTournamentMode() && $game->isFromPastBaseSeason()
            ),
            'gameCount' => $primaryCount,
            'maxGames' => $maxGames,
            'showAffiliateCta' => $showAffiliateCta,
        ]);
    }
}
