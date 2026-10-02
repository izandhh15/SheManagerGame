<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\Team;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameCreationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Creates an AFFILIATE career ("Carrera con Filiales"): a single save where
 * the manager starts in charge of the club's RESERVE side only.
 *
 * The first team is AI-managed with its own named coach (Team::manager_name).
 * There is no team switcher and no strict alternation: at the season
 * rollover, if the first team was relegated, finished in the relegation
 * zone, or ended far below the board's objective, the board sacks its coach
 * and hands the first team to the user (see AffiliateFirstTeamSackProcessor).
 * Otherwise the user stays another season with the reserve side.
 *
 * pair_mode = 'affiliate' marks the save so the UI renders filial-aware
 * labels; unlike dual mode there is no linked partner save.
 */
class InitAffiliateGame
{
    public function __construct(
        private readonly GameCreationService $gameCreationService,
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function __invoke(Request $request)
    {
        // A single save: same limit semantics as InitGame.
        $gameQuery = Game::where('user_id', $request->user()->id)->whereNull('deleting_at');
        if (Schema::hasColumn('games', 'linked_game_id')) {
            $gameQuery->whereNull('linked_game_id');
        }
        if ($gameQuery->count() >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        // Career access gates the save, exactly like InitGame.
        if (! $request->user()->canPlayCareerMode()) {
            return back()->withErrors(['game_mode' => __('messages.career_mode_requires_invite')]);
        }

        $request->validate([
            'club_id' => ['required', 'uuid'],
        ]);

        // The user picks the CLUB, but the save starts with its reserve side —
        // never a national side, a placeholder or the first team itself.
        $club = Team::where('type', '!=', 'national')
            ->where('is_placeholder', false)
            ->whereNull('parent_team_id')
            ->findOrFail($request->get('club_id'));

        $reserve = Team::where('parent_team_id', $club->id)
            ->where('is_placeholder', false)
            ->first();

        if (! $reserve) {
            return back()->withErrors(['club_id' => __('game.affiliate_no_reserve')]);
        }

        $game = $this->gameCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $reserve->id,
            gameMode: Game::MODE_CAREER,
        );

        $game->update(['pair_mode' => 'affiliate']);

        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, Game::MODE_CAREER);

        // Land on the reserve-side save: career flow starts with the welcome
        // tutorial, exactly like a standalone InitGame.
        return redirect()->route('game.welcome', $game->id);
    }
}
