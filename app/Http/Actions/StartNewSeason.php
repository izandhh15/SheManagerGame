<?php

namespace App\Http\Actions;

use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Season\Jobs\ProcessSeasonTransition;
use App\Models\Game;
use Illuminate\Support\Facades\Log;

class StartNewSeason
{
    public function __construct(
        private readonly PlayoffGeneratorFactory $playoffFactory,
        private readonly MatchFinalizationService $finalizationService,
        private readonly JobOfferService $jobOfferService,
    ) {}

    public function __invoke(string $gameId)
    {
        try {
            return $this->startSeason($gameId);
        } catch (\Throwable $e) {
            Log::error('StartNewSeason failed', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Clear the transition flag so the user isn't stuck.
            Game::where('id', $gameId)->update(['season_transitioning_at' => null]);

            return redirect()->route('show-game', $gameId)
                ->with('error', 'Error starting the new season: ' . $e->getMessage());
        }
    }

    private function startSeason(string $gameId)
    {
        // End-of-season entry point: finalize any match the user abandoned on
        // the live-match screen. Without this, its standings stay unapplied and
        // the closing pipeline reads a stale league table (off-by-one games),
        // which cascades into promotion/relegation imbalance errors.
        // Best-effort: a dropped DB connection here must not block the season
        // transition; the unplayed-matches guard below catches a truly stale table.
        try {
            $this->finalizationService->finalizePendingIfAny($gameId);
        } catch (\Throwable $e) {
            Log::warning('StartNewSeason: finalizePendingIfAny failed (non-fatal)', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
            ]);
        }

        $game = Game::findOrFail($gameId);

        // Verify all scheduled matches have been played. Catches the common
        // case of pending league rounds.
        $unplayedMatches = $game->matches()->where('played', false)->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', __('messages.season_not_complete'));
        }

        // Additional guard: every configured playoff must be resolved (final
        // CupTie.completed + winner_id set). Prevents firing the closing
        // pipeline while a playoff final is still awaiting resolution, which
        // would otherwise promote the wrong team via the "no playoff played"
        // fallback in the promotion rule.
        foreach ($this->playoffFactory->all() as $generator) {
            if ($generator->state($game) === PlayoffState::InProgress) {
                return redirect()->route('show-game', $gameId)
                    ->with('error', __('messages.season_not_complete'));
            }
        }

        // Pro-manager router: between seasons the user picks a team (or
        // stays) on /season-offers, and that choice is what triggers the
        // closing pipeline. Generate the offers on first visit; once the
        // user has resolved them (accepted one or declined all), fall
        // through to the pipeline-dispatch block below — Accept and Decline
        // both re-enter this action.
        if ($game->isProManagerMode() && !$this->jobOfferService->hasResolvedOffersFor($game)) {
            $this->jobOfferService->ensureEndOfSeasonOffersGenerated($game);
            return redirect()->route('game.season-offers', $gameId);
        }

        // Atomic check-and-set: only one request can win the race
        $updated = Game::where('id', $gameId)
            ->whereNull('season_transitioning_at')
            ->update(['season_transitioning_at' => now()]);

        if (! $updated) {
            return redirect()->route('show-game', $gameId);
        }

        ProcessSeasonTransition::dispatch($gameId);

        return redirect()->route('show-game', $gameId);
    }
}
