<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\NationalSquadService;
use App\Modules\Season\Services\TournamentCreationService;
use App\Models\Game;
use App\Models\Team;
use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates a national-team game (beta, World Cup qualifiers by confederation)
 * from the squad picker: validates the team is a real national side and that
 * the 23 player_ids are all eligible for it (2026 NT templates), then creates
 * the game with the call-up stored on it. The qualifier competition is
 * resolved from the team's FIFA confederation (WWCQ legacy alias for teams
 * without one).
 */
class InitNationalGame
{
    public function __construct(
        private readonly TournamentCreationService $tournamentCreationService,
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function __invoke(Request $request)
    {
        $gameQuery = Game::where('user_id', $request->user()->id)->whereNull('deleting_at');
        // The dual-mode worker adds games.linked_game_id with its own
        // migration; when the column exists, linked (secondary) games don't
        // count against the 5-game limit. The hasColumn guard keeps this
        // working if this code runs before that migration.
        if (Schema::hasColumn('games', 'linked_game_id')) {
            $gameQuery->whereNull('linked_game_id');
        }
        $gameCount = $gameQuery->count();
        if ($gameCount >= 5) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        if (! Competition::whereIn('id', TournamentCreationService::WQC_IDS)->exists()) {
            return back()->withErrors(['game_mode' => __('messages.tournament_mode_requires_access')]);
        }

        $request->validate([
            'team_id' => ['required', 'uuid'],
            'player_ids' => ['nullable', 'array', 'size:23'],
            'player_ids.*' => ['nullable', 'uuid'],
        ]);

        $team = Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->findOrFail($request->get('team_id'));

        $playerIds = array_values(array_unique(array_map('strval', $request->get('player_ids', []))));
        // Squad is picked a few days before each FIFA window, not at game
        // creation. If none provided, auto-pick the 23 highest-rated
        // eligible players as a provisional squad (skipping the injured).
        if (count($playerIds) !== NationalSquadService::SQUAD_SIZE) {
            $injuredIds = array_keys(NationalSquadService::injuredPlayersUntil(
                $request->user()->id,
                now()->format('Y-m-d'),
            ));
            $playerIds = NationalSquadService::provisionalSquad($team->id, $injuredIds, $request->user()->id);
        }
        if (count($playerIds) !== NationalSquadService::SQUAD_SIZE) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_need_23')]);
        }

        // Every player must be eligible for this nation (has a 2026 NT
        // template for the team) — prevents crafted POSTs calling up
        // ineligible players.
        $eligibleCount = DB::table('game_player_templates')
            ->where('season', NationalSquadService::TEMPLATE_SEASON)
            ->where('team_id', $team->id)
            ->whereIn('player_id', $playerIds)
            ->distinct()
            ->count('player_id');

        if ($eligibleCount !== NationalSquadService::SQUAD_SIZE) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_need_23')]);
        }

        $game = $this->tournamentCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $team->id,
            competitionId: TournamentCreationService::competitionIdForConfederation($team->confederation),
            squadPlayerIds: $playerIds,
        );

        // The creation-time pick counts as this window's convocatoria so
        // the per-window picker doesn't fire again immediately. Uses
        // creationWindow() — the same window the picker UI showed — or the
        // dashboard re-prompts for a "different" window (double convocatoria).
        $window = NationalSquadService::creationWindow($game->season ?? NationalSquadService::TEMPLATE_SEASON);
        if ($window) {
            $game->update(['national_squad_window' => $window['start']]);
        }

        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, Game::MODE_TOURNAMENT);

        return redirect()->route('show-game', $game->id);
    }
}
