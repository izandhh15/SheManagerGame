<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Modules\Manager\Services\AcademyCareerService;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameCreationService;
use App\Modules\Season\Services\TournamentCreationService;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class InitGame
{
    public function __construct(
        private readonly GameCreationService $gameCreationService,
        private readonly TournamentCreationService $tournamentCreationService,
        private readonly ActivationTracker $activationTracker,
        private readonly JobOfferService $jobOfferService,
        private readonly AcademyCareerService $academyCareerService,
    ) {}

    public function __invoke(Request $request)
    {
        // Only primary saves count against the 5-game limit: dual-mode
        // secondaries (games.linked_game_id not null) are bookkeeping for
        // the same career. The hasColumn guard keeps this working if the
        // code runs before the linked_game_id migration.
        $gameQuery = Game::where('user_id', $request->user()->id)->whereNull('deleting_at');
        if (Schema::hasColumn('games', 'linked_game_id')) {
            $gameQuery->whereNull('linked_game_id');
        }
        if ($gameQuery->count() >= 5) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        $request->validate([
            'team_id' => ['required_without:academy_club_id', 'nullable', 'uuid'],
            'game_mode' => ['sometimes', Rule::in([Game::MODE_CAREER, Game::MODE_TOURNAMENT, Game::MODE_CAREER_PRO])],
            'academy_club_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $gameMode = $request->get('game_mode', Game::MODE_CAREER);

        if ($gameMode === Game::MODE_CAREER && ! $request->user()->canPlayCareerMode()) {
            return back()->withErrors(['game_mode' => __('messages.career_mode_requires_invite')]);
        }

        if ($gameMode === Game::MODE_TOURNAMENT
            && (! config('game.tournament_mode_enabled') || ! $request->user()->canPlayTournamentMode())) {
            return back()->withErrors(['game_mode' => __('messages.tournament_mode_requires_access')]);
        }

        if ($gameMode === Game::MODE_CAREER_PRO) {
            if (! $request->user()->canPlayCareerMode()) {
                return back()->withErrors(['game_mode' => __('messages.career_mode_requires_invite')]);
            }

            // Academy career: the user picks a CLUB (e.g. FC Barcelona) and
            // starts at its lowest filial (e.g. Barça C). The club id comes
            // in as academy_club_id; we resolve the lowest filial here.
            $academyClubId = $request->get('academy_club_id');
            $academyCareerClubId = null;
            $teamId = $request->get('team_id');

            if ($academyClubId) {
                $club = Team::find($academyClubId);
                // A national side has no filials: findLowestFilial() would
                // return the national team itself and the career pipeline
                // would brick the save (A17). Academy careers start at a
                // real club's lowest filial, never at a national team.
                if (!$club || $club->isReserveTeam() || $club->type === 'national') {
                    return back()->withErrors(['academy_club_id' => __('messages.invalid_academy_club')]);
                }

                $lowestFilial = $this->academyCareerService->findLowestFilial($club);
                if (!$lowestFilial) {
                    return back()->withErrors(['academy_club_id' => __('messages.club_has_no_filial')]);
                }

                $teamId = $lowestFilial->id;
                $academyCareerClubId = $club->id;
            } else {
                // Server-side check that the submitted team is in the Local-tier
                // Primera RFEF pool — without this, a crafted POST could start a
                // Pro Manager career at any club, bypassing the entry-tier
                // constraint the end-of-season ladder is built on.
                if (! $this->jobOfferService->eligibleProManagerStartingTeamIds()->contains($teamId)) {
                    return back()->withErrors(['team_id' => __('messages.invalid_pro_manager_team')]);
                }
            }

            $game = $this->gameCreationService->create(
                userId: (string) $request->user()->id,
                teamId: $teamId,
                gameMode: Game::MODE_CAREER_PRO,
            );

            // Mark as academy career if applicable
            if ($academyCareerClubId) {
                $game->update(['academy_career_club_id' => $academyCareerClubId]);
            }

            $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, Game::MODE_CAREER_PRO);

            return redirect()->route('game.welcome', $game->id);
        }

        if ($gameMode === Game::MODE_TOURNAMENT) {
            $game = $this->tournamentCreationService->create(
                userId: (string) $request->user()->id,
                teamId: $request->get('team_id'),
            );

            $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, Game::MODE_TOURNAMENT);

            return redirect()->route('show-game', $game->id);
        }

        // A17: the career pipeline only handles clubs — national-team
        // templates are skipped during setup, so a national team_id bricks
        // the save (setup_completed_at stays null forever: endless
        // "preparing season" screen). Reject it here with a clear error
        // BEFORE anything is created. National sides are created through
        // InitNationalGame / InitDualGame instead.
        $careerTeam = Team::where('type', '!=', 'national')->find($request->get('team_id'));
        if (! $careerTeam) {
            return back()->withErrors(['team_id' => __('game.dual_invalid_club')]);
        }

        $game = $this->gameCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $request->get('team_id'),
            gameMode: $gameMode,
        );

        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, $gameMode);

        return redirect()->route('game.welcome', $game->id);
    }
}
