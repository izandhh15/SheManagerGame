<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameCreationService;
use App\Modules\Season\Services\GameDeletionService;
use App\Modules\Season\Services\NationalSquadService;
use App\Modules\Season\Services\TournamentCreationService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates a DUAL career: a club game (primary) + a national-team game
 * (secondary) for the same user in a single step.
 *
 * HONEST LIMITATION — the two saves are fully separate simulations: each
 * has its own squad, calendar, injuries and form. An injury at the club
 * does NOT carry over to the national team (and vice versa); each calendar
 * advances on its own.
 *
 * TURN ORDER IS STRICT, though: DualTurnService enforces chronological
 * alternation — the half whose next match is dated earlier must be played
 * first. During FIFA windows the club half freezes until the international
 * matches are played (ShowLineup / AdvanceMatchday / AdvanceBothMatchdays
 * bounce the user to the partner save).
 *
 * The link is ASYMMETRIC on purpose: the club game is the primary
 * (linked_game_id = null, counts against the 3-game limit) and the
 * national game is the secondary (linked_game_id = club game id, does NOT
 * consume a slot). Deleting one half deletes the pair (see DeleteGame).
 */
class InitDualGame
{
    public function __construct(
        private readonly GameCreationService $gameCreationService,
        private readonly TournamentCreationService $tournamentCreationService,
        private readonly GameDeletionService $gameDeletionService,
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function __invoke(Request $request)
    {
        // Limit check: only primary saves (linked_game_id null) count, like
        // InitGame and InitNationalGame. The hasColumn guard keeps this
        // working if the code runs before the linked_game_id migration.
        $gameQuery = Game::where('user_id', $request->user()->id)->whereNull('deleting_at');
        if (Schema::hasColumn('games', 'linked_game_id')) {
            $gameQuery->whereNull('linked_game_id');
        }
        if ($gameQuery->count() >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        // Career access gates the club half, exactly like InitGame.
        if (! $request->user()->canPlayCareerMode()) {
            return back()->withErrors(['game_mode' => __('messages.career_mode_requires_invite')]);
        }

        if (! Competition::whereIn('id', TournamentCreationService::WQC_IDS)->exists()) {
            return back()->withErrors(['game_mode' => __('messages.tournament_mode_requires_access')]);
        }

        $request->validate([
            'club_id' => ['required', 'uuid'],
            'national_team_id' => ['required', 'uuid'],
            'player_ids' => ['nullable', 'array', 'size:23'],
            'player_ids.*' => ['nullable', 'uuid'],
        ]);

        // Club half: a real, playable club — never a national side, a
        // placeholder or a reserve team (the career picker only offers those).
        $club = Team::where('type', '!=', 'national')
            ->where('is_placeholder', false)
            ->findOrFail($request->get('club_id'));
        if ($club->isReserveTeam()) {
            return back()->withErrors(['club_id' => __('game.dual_invalid_club')]);
        }

        // National half: same validation as InitNationalGame.
        $nationalTeam = Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->findOrFail($request->get('national_team_id'));

        $playerIds = array_values(array_unique(array_map('strval', $request->get('player_ids', []))));
        // Squad is picked a few days before each FIFA window, not at game
        // creation. Auto-pick the 23 highest-rated as provisional
        // (skipping the injured).
        if (count($playerIds) !== NationalSquadService::SQUAD_SIZE) {
            $injuredIds = array_keys(NationalSquadService::injuredPlayersUntil(
                $request->user()->id,
                now()->format('Y-m-d'),
            ));
            $playerIds = NationalSquadService::provisionalSquad($nationalTeam->id, $injuredIds);
        }
        if (count($playerIds) !== NationalSquadService::SQUAD_SIZE) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_need_23')]);
        }

        // Every player must be eligible for this nation (has a 2026 NT
        // template for the team) — mirrors InitNationalGame so crafted POSTs
        // can't call up ineligible players.
        $eligibleCount = DB::table('game_player_templates')
            ->where('season', NationalSquadService::TEMPLATE_SEASON)
            ->where('team_id', $nationalTeam->id)
            ->whereIn('player_id', $playerIds)
            ->distinct()
            ->count('player_id');

        if ($eligibleCount !== NationalSquadService::SQUAD_SIZE) {
            return back()->withErrors(['player_ids' => __('game.squad_picker_need_23')]);
        }

        $clubGame = $this->gameCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $club->id,
            gameMode: Game::MODE_CAREER,
        );

        try {
            $nationalGame = $this->tournamentCreationService->create(
                userId: (string) $request->user()->id,
                teamId: $nationalTeam->id,
                competitionId: TournamentCreationService::competitionIdForConfederation($nationalTeam->confederation),
                squadPlayerIds: $playerIds,
            );
        } catch (\Throwable $e) {
            // Roll back the club half: never leave the user with half a dual.
            // (The services dispatch their setup jobs inline, so a single DB
            // transaction can't cover both creates.)
            $this->gameDeletionService->delete($clubGame);

            // The national create may have persisted its Game row before the
            // setup job threw (sync queue): remove that half too, otherwise
            // the user ends up with an orphaned national game stuck in setup.
            $orphan = Game::where('user_id', (string) $request->user()->id)
                ->where('team_id', $nationalTeam->id)
                ->where('game_mode', Game::MODE_TOURNAMENT)
                ->whereNull('setup_completed_at')
                ->where('created_at', '>', now()->subMinutes(15))
                ->latest('created_at')
                ->first();
            if ($orphan) {
                $this->gameDeletionService->delete($orphan);
            }

            throw $e;
        }

        // ASYMMETRIC LINK: the national game points at the club game.
        // linked_game_id lives only on the secondary so it never consumes a
        // game slot; the way back uses Game::dualPartner().
        $nationalGame->update(['linked_game_id' => $clubGame->id]);

        // The creation-time pick counts as this window's convocatoria so
        // the per-window picker doesn't fire again immediately.
        $window = NationalSquadService::relevantWindow($nationalGame);
        if ($window) {
            $nationalGame->update(['national_squad_window' => $window['start']]);
        }

        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $clubGame->id, Game::MODE_CAREER);
        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $nationalGame->id, Game::MODE_TOURNAMENT);

        // Land on the club game: career flow starts with the welcome
        // tutorial, exactly like a standalone InitGame.
        return redirect()->route('game.welcome', $clubGame->id);
    }
}
