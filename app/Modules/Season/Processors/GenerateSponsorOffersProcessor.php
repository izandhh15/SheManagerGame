<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Commercial\Services\SponsorService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;

/**
 * Rolls shirt / ad-board sponsor deals over into the new season (expiring
 * finished deals, offering incumbents a free renewal, clearing stale
 * offers) and seeds the first offer board for each slot. Runs after the
 * naming-rights rollover (105) and before BudgetProjectionProcessor (107)
 * so fresh active deals are counted in the projection.
 */
class GenerateSponsorOffersProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly SponsorService $sponsorService,
    ) {}

    public function priority(): int
    {
        return 106;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Tournament-mode games have no club economy / commercial hub.
        if ($game->isTournamentMode()) {
            return $data;
        }

        $this->sponsorService->seedSeasonBoard($game);

        return $data;
    }
}
