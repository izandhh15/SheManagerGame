<?php

namespace App\Modules\Match\Jobs;

use App\Events\SeasonCompleted;
use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\AffiliateMidSeasonSackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs a single matchday advance. Dispatched via MatchdayAdvanceCoordinator —
 * async (queued) from AdvanceMatchday, or sync (handle() invoked directly via
 * the container) from AdvanceFastMatchday and console commands. Callers must
 * claim matchday_advancing_at first; the job bails if the flag is not set.
 */
class ProcessMatchdayAdvance implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 180;

    public function __construct(
        public string $gameId,
        public bool $fastForward = false,
    ) {
        $this->onQueue('gameplay');
    }

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    public function tags(): array
    {
        return ['game:' . $this->gameId];
    }

    public function handle(
        MatchdayOrchestrator $orchestrator,
        ActivationTracker $activationTracker,
        AffiliateMidSeasonSackService $midSeasonSack,
    ): ?MatchdayAdvanceResult {
        $game = Game::find($this->gameId);

        if (! $game || ! $game->isAdvancingMatchday()) {
            return null;
        }

        try {
            $result = $orchestrator->advance($game, fastForward: $this->fastForward);

            $game->refresh();
            $this->maybeTriggerAffiliateMidSeasonSack($game, $result, $midSeasonSack);
            $this->dispatchSeasonCompletedIfDone($game, $result);
            $this->recordActivationEvents($game, $activationTracker);

            $game->update([
                'matchday_advance_result' => $result->toArray(),
                'matchday_advancing_at' => null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Game::where('id', $this->gameId)->update([
                'matchday_advancing_at' => null,
                'matchday_advance_result' => null,
            ]);

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Game::where('id', $this->gameId)->update([
            'matchday_advancing_at' => null,
            'matchday_advance_result' => null,
        ]);

        Log::error('Matchday advance failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }

    /**
     * Affiliate career ("Carrera con Filiales"): after every simulated
     * matchday the first-team board may sack its coach mid-season and hand
     * the job to the user. Skipped when nothing was simulated, when the
     * season just ended (the season-end sack evaluation owns that moment),
     * and when a live match awaits the user's click — switching teams would
     * yank the dugout from under them mid-flow, so it waits for the next
     * advance instead.
     */
    private function maybeTriggerAffiliateMidSeasonSack(
        Game $game,
        MatchdayAdvanceResult $result,
        AffiliateMidSeasonSackService $midSeasonSack,
    ): void {
        if (in_array($result->type, ['blocked', 'live_match', 'season_complete'], true)) {
            return;
        }

        if ($game->pending_finalization_match_id) {
            return;
        }

        $midSeasonSack->trigger($game);
    }

    /**
     * The orchestrator returns `done` whenever the user has no more matches
     * this matchday — including every fast-mode click and mid-season cup
     * eliminations. Guard the event behind an "actually no matches left"
     * check so listeners (other-leagues sim, activation analytics) only run
     * once per season.
     */
    private function dispatchSeasonCompletedIfDone(Game $game, MatchdayAdvanceResult $result): void
    {
        if ($result->type !== 'season_complete' && $result->type !== 'done') {
            return;
        }

        if ($result->type === 'done' && $game->matches()->where('played', false)->exists()) {
            return;
        }

        // Tournament mode has its own end-of-run event chain (TournamentEnded →
        // snapshot/soft-delete listeners). Suppressing SeasonCompleted here keeps
        // behavior consistent with the live path (FinalizeMatch does the same).
        if ($game->isTournamentMode()) {
            return;
        }

        event(new SeasonCompleted($game));
    }

    private function recordActivationEvents(Game $game, ActivationTracker $activationTracker): void
    {
        $activationTracker->record(
            $game->user_id,
            ActivationEvent::EVENT_FIRST_MATCH_PLAYED,
            $game->id,
            $game->game_mode,
        );

        $alreadyRecorded = ActivationEvent::where('user_id', $game->user_id)
            ->where('game_id', $game->id)
            ->where('event', ActivationEvent::EVENT_5_MATCHES_PLAYED)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $matchesPlayed = GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                ->orWhere('away_team_id', $game->team_id))
            ->count();

        if ($matchesPlayed >= 5) {
            $activationTracker->record(
                $game->user_id,
                ActivationEvent::EVENT_5_MATCHES_PLAYED,
                $game->id,
                $game->game_mode,
            );
        }
    }
}
