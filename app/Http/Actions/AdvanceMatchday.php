<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Government\Services\GovernmentFriendlyService;
use App\Modules\Government\Services\GovernmentVenueService;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;
use App\Modules\Media\Services\JournalistService;
use App\Modules\Season\Services\DualTurnService;

class AdvanceMatchday
{
    public function __construct(
        private readonly MatchdayAdvanceCoordinator $coordinator,
        private readonly DualTurnService $dualTurn,
        private readonly GovernmentFriendlyService $governmentFriendly,
        private readonly GovernmentVenueService $governmentVenue,
        private readonly JournalistService $journalists,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // If the user is in fast mode, the normal advance path is disabled —
        // they must explicitly exit fast mode (or use the fast-mode advance
        // route) to play matches.
        if ($game->isFastMode()) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        // Dual mode: strict club ⇄ nation alternation. The half whose next
        // match comes later cannot be advanced while the partner has an
        // earlier pending match — bounce to the partner's save.
        if ($forced = $this->dualTurn->mustPlayPartnerFirst($game)) {
            return redirect()->route('show-game', $forced->id)
                ->with('dual_forced', [
                    'team' => $forced->team->name,
                    'from' => $game->team->name,
                    'to_nation' => $forced->team->type === 'national',
                ]);
        }

        // Run inline: with sibling matches on the AIMatchResolver fast path,
        // a full matchday advance completes sub-second, so the queue hop +
        // 2s polling cycle cost more than the work itself. A client-side
        // overlay in game-header shows the branded loading screen while this
        // request is in flight. runSync returning null (another request
        // already holds the flag) falls through to ShowGame, which renders
        // game-loading-matchday and polls — the existing safety net.
        $this->coordinator->runSync($gameId);

        // F4: national teams without a competition in progress may receive
        // a government-paid friendly offer (via in-game mail).
        $this->governmentFriendly->maybeOffer($game->fresh());

        // F5: governments occasionally offer a venue for the next home match.
        $this->governmentVenue->maybeOffer($game->fresh());

        // National press: preview of the upcoming national-team match
        // (no-op for club saves or when already posted).
        $this->journalists->maybePostNationalPreview($game->fresh());

        return redirect()->route('show-game', $gameId);
    }
}
