<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;
use App\Modules\Match\Services\MatchdayService;
use Illuminate\Http\Request;

/**
 * Advances the dual save chronologically: the half (club or national team)
 * whose next match is earlier gets advanced first. During FIFA windows the
 * national-team match comes first; otherwise the club match does. This
 * interleaves the two calendars like a real manager's season instead of
 * advancing both in lockstep.
 */
class AdvanceBothMatchdays
{
    public function __construct(
        private readonly MatchdayAdvanceCoordinator $coordinator,
        private readonly MatchdayService $matchdayService,
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
        if ($partner->isFastMode()) {
            $this->coordinator->runSync($gameId);

            return redirect()->route('show-game', $gameId);
        }

        // Chronological interleaving: advance the half with the earlier
        // next match. If both fall on the same day, advance both.
        $nextA = $this->matchdayService->getNextPlayerMatch($game);
        $nextB = $this->matchdayService->getNextPlayerMatch($partner);

        $dateA = $nextA?->scheduled_date?->format('Y-m-d');
        $dateB = $nextB?->scheduled_date?->format('Y-m-d');

        if ($nextA && (! $nextB || $dateA < $dateB)) {
            // Club (or whichever is earlier) goes first.
            $this->coordinator->runSync($game->id);

            return redirect()->route('show-game', $game->id);
        }

        if ($nextB && (! $nextA || $dateB < $dateA)) {
            // Partner's match is earlier.
            $this->coordinator->runSync($partner->id);

            return redirect()->route('show-game', $partner->id);
        }

        // Same day (or neither has a next match): advance both in lockstep.
        $this->coordinator->runSync($game->id);
        $this->coordinator->runSync($partner->id);

        return redirect()->route('show-game', $gameId);
    }
}
