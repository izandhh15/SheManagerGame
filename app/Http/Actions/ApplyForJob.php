<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\Team;
use App\Modules\Manager\Services\JobApplicationService;
use Illuminate\Http\Request;

/**
 * Apply for a job at another club. The application is resolved immediately:
 * accepted (stage the team switch) or rejected. If accepted, there's a
 * chance the current club finds out — and if they're happy with you, they
 * might fire you on the spot for the betrayal.
 */
class ApplyForJob
{
    public function __construct(
        private readonly JobApplicationService $jobApplicationService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $teamId)
    {
        $game = Game::where('id', $gameId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($game->isProManagerMode(), 404);

        $team = Team::findOrFail($teamId);

        // Can't apply to your own club
        if ($team->id === $game->team_id) {
            return back()->withErrors(['team' => __('messages.cannot_apply_to_own_club')]);
        }

        $result = $this->jobApplicationService->apply($game, $team);

        if ($result['fired']) {
            // The club found out and fired you for the betrayal.
            // Create post-firing offers (worse clubs) via the disaster grade.
            // The accepted application is void — you're fired.
            $result['offer']->update(['status' => \App\Models\ManagerJobOffer::STATUS_REJECTED]);
            $game->update(['pending_team_switch' => null]);

            // Generate post-firing offers (worse clubs, as requested)
            app(\App\Modules\Manager\Services\JobOfferService::class)
                ->generateEndOfSeasonOffers($game, 'disaster');

            return redirect()->route('game.job-market', $game->id)
                ->with('job_result', [
                    'accepted' => true,
                    'discovered' => true,
                    'fired' => true,
                    'team_name' => $team->name,
                ]);
        }

        return redirect()->route('game.job-market', $game->id)
            ->with('job_result', [
                'accepted' => $result['accepted'],
                'discovered' => $result['discovered'],
                'fired' => false,
                'team_name' => $team->name,
            ]);
    }
}
