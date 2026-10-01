<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Season\Services\NationalSquadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Updates the national-team squad (convocatoria) for an existing game.
 *
 * Called at each FIFA international break so the user picks a fresh 23
 * instead of keeping the initial squad for the whole season. Injured
 * players cannot be called up until they recover, and the new 23 is
 * synced into the match squad (new call-ups are created from templates,
 * dropped players keep their rows flagged out of the squad).
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

        if (count($playerIds) !== NationalSquadService::SQUAD_SIZE) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_need_23')]);
        }

        // Validate all 23 belong to this national team (2026 NT templates).
        $teamId = $game->team_id;
        $validCount = DB::table('game_player_templates')
            ->where('season', NationalSquadService::TEMPLATE_SEASON)
            ->where('team_id', $teamId)
            ->whereIn('player_id', $playerIds)
            ->count();

        if ($validCount !== NationalSquadService::SQUAD_SIZE) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_invalid')]);
        }

        // Injured players can't be called up until they recover: check
        // every save of the user (club and national) for active injuries.
        $window = NationalSquadService::relevantWindow($game);
        $injured = NationalSquadService::injuredPlayersUntil(
            $request->user()->id,
            $window['start'] ?? ($game->current_date ?? now())->format('Y-m-d'),
        );
        if (array_intersect($playerIds, array_keys($injured)) !== []) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_injured_error')]);
        }

        DB::transaction(function () use ($game, $playerIds, $window) {
            // Sync the match squad: insert new call-ups, flag out the dropped.
            NationalSquadService::syncSquadPlayers($game, $playerIds);

            $game->national_squad_player_ids = $playerIds;
            // Mark the window as confirmed so the picker isn't prompted
            // again for it.
            if ($window) {
                $game->national_squad_window = $window['start'];
            }
            $game->save();
        });

        return redirect()->route('show-game', $game->id)
            ->with('status', __('game.squad_updated'));
    }
}
