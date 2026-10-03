<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Modules\Manager\Services\AcademyCareerService;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameCreationService;
use App\Modules\Season\Services\TournamentCreationService;
use App\Modules\Squad\Services\SquadMinimumService;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            // M38/M39: the resolved starting team (lowest filial for academy
            // careers, chosen club otherwise) must be set-up-able. Reject
            // with a clear error BEFORE anything is created.
            $proTeam = Team::find($teamId);
            if ($proTeam && ($blocker = $this->careerSetupBlocker($proTeam))) {
                return back()->withErrors(['team_id' => __("messages.{$blocker['key']}", $blocker['params'])]);
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

        // M38/M39: fail fast with a clear error instead of a bricked save:
        // - M39: a team with no competition_teams link gets zero fixtures
        //   but setup "completes" → unplayable save.
        // - M38: a team with < 17 player templates makes
        //   YouthAcademyPromotionProcessor generate synthetic players while
        //   $game->current_date is still null → 500 `copy() on null` and a
        //   save stuck in "preparing season" forever.
        if ($blocker = $this->careerSetupBlocker($careerTeam)) {
            return back()->withErrors(['team_id' => __("messages.{$blocker['key']}", $blocker['params'])]);
        }

        $game = $this->gameCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $request->get('team_id'),
            gameMode: $gameMode,
        );

        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, $gameMode);

        return redirect()->route('game.welcome', $game->id);
    }

    /**
     * M38/M39: pre-creation sanity check for career saves. Returns
     * ['key' => <messages.* key>, 'params' => [...]] when the team cannot
     * produce a playable save, null when it is fine.
     *
     * The competition lookup mirrors GameCreationService::create() so the
     * check and the creation agree on which competition (and season) the
     * team would start in.
     */
    private function careerSetupBlocker(Team $team): ?array
    {
        $competitionTeam = CompetitionTeam::forCurrentSeason()->where('team_id', $team->id)
            ->whereHas('competition', fn($q) => $q->where('role', Competition::ROLE_LEAGUE)->where('tier', 1))
            ->first()
            ?? CompetitionTeam::forCurrentSeason()->where('team_id', $team->id)
                ->whereHas('competition', fn($q) => $q->where('role', Competition::ROLE_PRIMARY))
                ->first()
            ?? CompetitionTeam::forCurrentSeason()->where('team_id', $team->id)->first();

        // M39: no competition link → the fixture generator never creates a
        // single match for this team, but setup would still "complete".
        if (! $competitionTeam) {
            return ['key' => 'team_has_no_competition_link', 'params' => []];
        }

        // M38: fewer templates than the squad minimum → the setup pipeline
        // tries to fill the gap with synthetic players while
        // $game->current_date is still null → `copy() on null` → 500.
        $templateCount = DB::table('game_player_templates')
            ->where('season', $competitionTeam->season)
            ->where('team_id', $team->id)
            ->count();

        if ($templateCount < SquadMinimumService::MIN_SQUAD_SIZE) {
            return [
                'key' => 'team_squad_too_small',
                'params' => [
                    'count' => $templateCount,
                    'minimum' => SquadMinimumService::MIN_SQUAD_SIZE,
                ],
            ];
        }

        return null;
    }
}
