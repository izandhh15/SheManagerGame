<?php

namespace App\Http\Actions;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Updates the national-team squad (convocatoria) for an existing game.
 *
 * Called at each FIFA international break so the user picks a fresh 23
 * instead of keeping the initial squad for the whole season.
 */
class UpdateNationalSquad
{
    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::where('id', $gameId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Only national-team games (or dual secondary) have squads.
        if (!$game->national_squad_player_ids && $game->team->type !== 'national') {
            abort(403, 'This game has no national squad.');
        }

        $playerIds = $request->input('player_ids', []);
        if (!is_array($playerIds)) {
            $playerIds = [];
        }
        // The picker sends player_id strings; normalize.
        $playerIds = array_values(array_unique(array_map('strval', $playerIds)));

        if (count($playerIds) !== 23) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_need_23')]);
        }

        // Validate all 23 belong to this national team (2026 NT templates).
        $teamId = $game->team_id;
        $validCount = DB::table('game_player_templates')
            ->where('season', '2026')
            ->where('team_id', $teamId)
            ->whereIn('player_id', $playerIds)
            ->count();

        if ($validCount !== 23) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_invalid')]);
        }

        $game->national_squad_player_ids = $playerIds;
        // Mark the upcoming window as confirmed so the picker isn't
        // prompted again for it.
        $season = $game->season ?? '2026';
        $today = ($game->current_date ?? now())->format('Y-m-d');
        $upcoming = \App\Modules\Competition\Configs\FifaInternationalBreaks::upcomingWithin($season, $today, 7)
            ?? \App\Modules\Competition\Configs\FifaInternationalBreaks::upcomingWithin($season, $today, 60);
        if ($upcoming) {
            $game->national_squad_window = $upcoming['start'];
        }
        $game->save();

        return redirect()->route('show-game', $game->id)
            ->with('status', __('game.squad_updated'));
    }
}
