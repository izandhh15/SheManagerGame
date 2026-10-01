<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Manager\Services\JobApplicationService;

/**
 * Job market: lists clubs the manager can apply to mid-season.
 * Only for pro-manager mode.
 */
class ShowJobMarket
{
    public function __construct(
        private readonly JobApplicationService $jobApplicationService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        abort_unless($game->isProManagerMode(), 404);
        abort_if($game->isTournamentMode(), 404);

        $jobs = $this->jobApplicationService->getAvailableJobs($game);

        return view('job-market', [
            'game' => $game,
            'jobs' => $jobs,
        ]);
    }
}
