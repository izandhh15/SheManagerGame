<?php

namespace App\Modules\Academy\Listeners;

use App\Events\SeasonStarted;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Academy\Services\YouthAcademyService;

class GenerateInitialAcademyBatch
{
    public function __construct(
        private readonly YouthAcademyService $youthAcademyService,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(SeasonStarted $event): void
    {
        $game = $event->game;

        // R9: academies only exist for clubs. National sides must never get
        // synthetic youth players — YouthAcademyPromotionProcessor would
        // later promote them into GamePlayer of the national team (players
        // must be 100% real, never invented).
        if (($game->team?->type ?? null) !== 'club') {
            return;
        }

        $batch = $this->youthAcademyService->generateSeasonBatch($game);

        if ($batch->isNotEmpty()) {
            $this->notificationService->notifyAcademyBatch($game, $batch->count());
        }

        $jewel = $batch->first(fn ($prospect) => $prospect instanceof \App\Models\AcademyPlayer && $prospect->is_jewel);

        if ($jewel) {
            $this->notificationService->notifyAcademyJewel($game, $jewel);
        }
    }
}
