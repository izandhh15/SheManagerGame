<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Commercial\Services\SponsorReadService;
use App\Modules\Stadium\Services\NamingRightsReadService;

/**
 * Commercial hub: where the manager grows recurring income — stadium naming
 * rights (sought proactively) plus the two sponsor slots whose offers
 * arrive on their own each month: shirt sponsor and ad boards. Reads the
 * commercial panel from NamingRightsReadService and SponsorReadService.
 */
class ShowClubCommercial
{
    public function __construct(
        private readonly NamingRightsReadService $namingRightsReadService,
        private readonly SponsorReadService $sponsorReadService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $panel = $this->namingRightsReadService->buildCommercialPanel($game)['namingRights'];
        $sponsors = $this->sponsorReadService->buildSponsorPanel($game)['slots'];

        return view('club.commercial', [
            'game' => $game,
            'namingRights' => $panel,
            'sponsors' => $sponsors,
        ]);
    }
}
