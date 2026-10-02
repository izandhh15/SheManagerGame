<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A reserve (filial) team requests to play a home match at its parent
 * first team's stadium. The parent is AI-managed, so it decides: it
 * almost always accepts for its own filial unless its own team needs
 * the ground that day.
 */
class RequestParentStadium
{
    public function __invoke(Request $request, string $gameId): RedirectResponse
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $validated = $request->validate([
            'match_id' => ['required', 'string'],
        ]);

        $match = GameMatch::where('game_id', $game->id)
            ->where('id', $validated['match_id'])
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->first();

        if (! $match) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __('game.parent_stadium_no_match'));
        }

        $parent = $game->team?->parentTeam;
        if (! $parent || ! $parent->stadium_name) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __('game.parent_stadium_no_parent'));
        }

        // Already moved to another ground?
        if ($match->neutral_venue_name) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __('game.parent_stadium_already_moved'));
        }

        // Is the parent's ground free that day? The parent's own home
        // matches (any game) block it.
        $matchDate = substr((string) $match->scheduled_date, 0, 10);
        $parentBusy = GameMatch::where('home_team_id', $parent->id)
            ->where('played', false)
            ->whereDate('scheduled_date', $matchDate)
            ->exists();

        if ($parentBusy) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __('game.parent_stadium_busy', [
                    'club' => $parent->name,
                ]));
        }

        $match->update([
            'neutral_venue_name' => $parent->stadium_name,
            'neutral_venue_capacity' => (int) $parent->stadium_seats,
        ]);

        return redirect()->route('game.club.stadium', $gameId)
            ->with('success', __('game.parent_stadium_accepted', [
                'stadium' => $parent->stadium_name,
                'club' => $parent->name,
            ]));
    }
}
