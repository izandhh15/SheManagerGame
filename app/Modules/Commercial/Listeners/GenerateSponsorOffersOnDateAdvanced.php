<?php

namespace App\Modules\Commercial\Listeners;

use App\Modules\Commercial\Services\SponsorService;
use App\Modules\Match\Events\GameDateAdvanced;

/**
 * Monthly sponsor arrivals: whenever the game date crosses into a new
 * calendar month, top up the shirt / ad-board offer boards with fresh bids
 * at the club's current sponsor tier (division + league position) and
 * notify the manager. SponsorService::generateMonthlyOffers is idempotent
 * per month, so this is safe to run on every date advance.
 */
class GenerateSponsorOffersOnDateAdvanced
{
    public function __construct(
        private readonly SponsorService $sponsorService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        if ($event->previousDate->isSameMonth($event->newDate)) {
            return;
        }

        $this->sponsorService->generateMonthlyOffers($event->game);
    }
}
