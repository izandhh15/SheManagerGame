<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;
use Illuminate\Http\Request;

/**
 * Advances one matchday on BOTH halves of a dual save (the current game and
 * its linked partner). Each save is advanced with the same
 * MatchdayAdvanceCoordinator::runSync() the single advance button uses —
 * the two simulations stay fully independent; this just keeps their
 * calendars in lockstep, one matchday at a time.
 */
class AdvanceBothMatchdays
{
    public function __construct(
        private readonly MatchdayAdvanceCoordinator $coordinator,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if ((int) $game->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $partner = $game->dualPartner();

        if (! $partner) {
            // Not a dual save after all: same behaviour as AdvanceMatchday.
            $this->coordinator->runSync($gameId);

            return redirect()->route('show-game', $gameId);
        }

        if ((int) $partner->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        // Parity with AdvanceMatchday: the fast-mode advance flow is
        // separate, so a fast-mode game redirects there instead.
        if ($game->isFastMode()) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        $this->coordinator->runSync($gameId);

        // Fast mode has its own advance action (AdvanceFastMatchday) with
        // different semantics; it can't run for two saves at once, so a
        // fast-mode partner is left for its own flow.
        if (! $partner->isFastMode()) {
            $this->coordinator->runSync($partner->id);
        }

        return redirect()->route('show-game', $gameId);
    }
}
