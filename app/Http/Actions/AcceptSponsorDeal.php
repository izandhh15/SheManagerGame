<?php

namespace App\Http\Actions;

use App\Http\Actions\Concerns\HandlesCommercialCommit;
use App\Models\Game;
use App\Modules\Commercial\Services\SponsorService;
use Illuminate\Http\Request;

class AcceptSponsorDeal
{
    use HandlesCommercialCommit;

    public function __construct(
        private readonly SponsorService $sponsorService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $validated = $request->validate([
            'deal_id' => 'required|string',
        ]);

        $sponsorName = null;
        $slot = null;
        if ($redirect = $this->safeCommit($gameId, function () use ($game, $validated, &$sponsorName, &$slot) {
            $deal = $this->sponsorService->acceptOffer($game, $validated['deal_id']);
            $sponsorName = $deal->sponsor_name;
            $slot = $deal->slot;
        })) {
            return $redirect;
        }

        return $this->commercialSuccess($gameId, 'messages.sponsor_deal_accepted', [
            'sponsor' => $sponsorName,
            'slot' => __("club.commercial.slot_{$slot}"),
        ]);
    }
}
