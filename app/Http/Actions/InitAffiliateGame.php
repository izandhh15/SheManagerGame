<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\Team;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameCreationService;
use App\Modules\Season\Services\GameDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Creates an AFFILIATE career: a first-team game (primary) + the club's
 * reserve team game (secondary) for the same user in a single step.
 *
 * The two saves are fully separate simulations (own squad, calendar,
 * injuries, form) but stay linked like a dual pair: the ASYMMETRIC link
 * (secondary.linked_game_id = primary) means the reserve half doesn't
 * consume a game slot, DualTurnService's strict alternation applies, and
 * deleting one half deletes the pair. The only difference from dual mode
 * is pair_mode = 'affiliate', which the UI reads to render filial-aware
 * labels ("Primer equipo" / "Filial") instead of club/national ones.
 */
class InitAffiliateGame
{
    public function __construct(
        private readonly GameCreationService $gameCreationService,
        private readonly GameDeletionService $gameDeletionService,
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function __invoke(Request $request)
    {
        // Same limit semantics as InitGame: only primary saves count.
        $gameQuery = Game::where('user_id', $request->user()->id)->whereNull('deleting_at');
        if (Schema::hasColumn('games', 'linked_game_id')) {
            $gameQuery->whereNull('linked_game_id');
        }
        if ($gameQuery->count() >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        // Career access gates the first-team half, exactly like InitGame.
        if (! $request->user()->canPlayCareerMode()) {
            return back()->withErrors(['game_mode' => __('messages.career_mode_requires_invite')]);
        }

        $request->validate([
            'club_id' => ['required', 'uuid'],
        ]);

        // First team: a real, playable club — never a national side, a
        // placeholder or a reserve team.
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

        $firstGame = $this->gameCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $club->id,
            gameMode: Game::MODE_CAREER,
        );

        try {
            $reserveGame = $this->gameCreationService->create(
                userId: (string) $request->user()->id,
                teamId: $reserve->id,
                gameMode: Game::MODE_CAREER,
            );
        } catch (\Throwable $e) {
            // Never leave the user with half an affiliate pair.
            $this->gameDeletionService->delete($firstGame);

            throw $e;
        }

        // ASYMMETRIC LINK + affiliate mode flag on both halves.
        $reserveGame->update([
            'linked_game_id' => $firstGame->id,
            'pair_mode' => 'affiliate',
        ]);
        $firstGame->update(['pair_mode' => 'affiliate']);

        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $firstGame->id, Game::MODE_CAREER);
        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $reserveGame->id, Game::MODE_CAREER);

        // Land on the first-team game: career flow starts with the welcome
        // tutorial, exactly like a standalone InitGame.
        return redirect()->route('game.welcome', $firstGame->id);
    }
}
