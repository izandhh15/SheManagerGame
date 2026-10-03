<?php

namespace App\Modules\Season\Services;

use App\Jobs\DeleteGameJob;
use App\Models\Game;
use App\Modules\Manager\Services\PerformanceHistoryService;
use App\Modules\Social\Services\CareerHistoryService;
use Illuminate\Support\Facades\Cache;

class GameDeletionService
{
    public function __construct(
        private readonly CareerHistoryService $careerHistory,
    ) {}

    public function delete(Game $game): void
    {
        if ($game->isDeleting()) {
            return;
        }

        // Snapshot the career before the async job wipes the rows: friends
        // (and the owner) keep a history of deleted careers.
        $this->careerHistory->snapshotOnDelete($game->loadMissing('team'));

        Cache::forget("game_owner:{$game->id}");
        PerformanceHistoryService::forget($game->id);

        $game->update(['deleting_at' => now()]);

        DeleteGameJob::dispatch($game->id);
    }
}
