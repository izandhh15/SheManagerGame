<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\TournamentCreationService;
use App\Models\Game;
use App\Models\Team;
use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Creates a national-team game (beta, WWCQ qualifiers) from the squad
 * picker: validates the team is a real national side and that the 23
 * player_ids are all eligible for it (2026 NT templates), then creates
 * the game with the call-up stored on it.
 */
class InitNationalGame
{
    public function __construct(
        private readonly TournamentCreationService $tournamentCreationService,
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function __invoke(Request $request)
    {
        $gameCount = Game::where('user_id', $request->user()->id)->whereNull('deleting_at')->count();
        if ($gameCount >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        if (! Competition::where('id', 'WWCQ')->exists()) {
            return back()->withErrors(['game_mode' => __('messages.tournament_mode_requires_access')]);
        }

        $request->validate([
            'team_id' => ['required', 'uuid'],
            'player_ids' => ['required', 'array', 'size:23'],
            'player_ids.*' => ['required', 'uuid'],
        ]);

        $team = Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->findOrFail($request->get('team_id'));

        $playerIds = array_values(array_unique($request->get('player_ids')));
        if (count($playerIds) !== 23) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_need_23')]);
        }

        // Every player must be eligible for this nation (has a 2026 NT
        // template for the team) — prevents crafted POSTs calling up
        // ineligible players.
        $eligibleCount = DB::table('game_player_templates')
            ->where('season', '2026')
            ->where('team_id', $team->id)
            ->whereIn('player_id', $playerIds)
            ->distinct()
            ->count('player_id');

        if ($eligibleCount !== 23) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_need_23')]);
        }

        $game = $this->tournamentCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $team->id,
            competitionId: 'WWCQ',
            squadPlayerIds: $playerIds,
        );

        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, Game::MODE_TOURNAMENT);

        return redirect()->route('show-game', $game->id);
    }
}
