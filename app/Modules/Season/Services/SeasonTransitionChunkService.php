<?php

namespace App\Modules\Season\Services;

use App\Events\SeasonStarted;
use App\Models\Game;
use App\Models\SeasonArchive;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Illuminate\Support\Facades\Log;

/**
 * Time-boxed season transition runner for serverless environments.
 *
 * The full transition (30+ closing processors + setup processors) takes
 * minutes, far beyond serverless HTTP timeouts (60s on Vercel Hobby).
 * This service runs as much as fits in a time budget, persisting a
 * checkpoint after each processor, so the frontend can poll and resume
 * until the transition completes.
 */
class SeasonTransitionChunkService
{
    public function __construct(
        private readonly SeasonClosingPipeline $closingPipeline,
        private readonly SeasonSetupPipeline $setupPipeline,
        private readonly PlayoffGeneratorFactory $playoffFactory,
    ) {}

    /**
     * Run transition work for up to $maxSeconds.
     *
     * @return array{done: bool, step: int|null, totalSteps: int}
     */
    public function runChunk(Game $game, float $maxSeconds = 25.0): array
    {
        $totalSteps = count($this->closingPipeline->getProcessors())
            + count($this->setupPipeline->getProcessors());

        if (!$game->isTransitioningSeason()) {
            return ['done' => true, 'step' => null, 'totalSteps' => $totalSteps];
        }

        // Guard: abort cleanly if a playoff is still in progress.
        foreach ($this->playoffFactory->all() as $generator) {
            if ($generator->state($game) === PlayoffState::InProgress) {
                Log::warning('Aborting season transition chunk: playoff still in progress', [
                    'game_id' => $game->id,
                ]);
                $game->update(['season_transitioning_at' => null]);
                return ['done' => true, 'step' => null, 'totalSteps' => $totalSteps];
            }
        }

        $chunkStart = microtime(true);
        $lastStep = $game->season_transition_step ?? -1;
        $restoredData = $this->restoreTransitionData($game);
        $closingProcessorCount = count($this->closingPipeline->getProcessors());

        // Phase 1: Close old season (skip if fully completed in a previous chunk).
        if ($lastStep < $closingProcessorCount - 1) {
            $remaining = $maxSeconds - (microtime(true) - $chunkStart);
            if ($remaining <= 0) {
                return $this->progress($game, $totalSteps);
            }
            $data = $this->closingPipeline->run($game, $lastStep, $restoredData, $remaining);
            $game->refresh()->setRelations([]);
            $lastStep = $game->season_transition_step ?? -1;

            // Budget spent during closing: stop here, resume next chunk.
            if ($lastStep < $closingProcessorCount - 1) {
                return $this->progress($game, $totalSteps);
            }
        } else {
            $data = $restoredData ?? new SeasonTransitionData(
                oldSeason: $game->season,
                newSeason: $game->season,
                competitionId: $game->competition_id,
            );
        }

        // Advance game to the new season (idempotent: safe to repeat).
        $game->refresh()->setRelations([]);
        $game->update(['season' => $data->newSeason]);

        // Phase 2: Set up new season.
        $remaining = $maxSeconds - (microtime(true) - $chunkStart);
        if ($remaining > 0) {
            $game->refresh()->setRelations([]);
            $this->setupPipeline->run($game, $data, $closingProcessorCount, $lastStep, $remaining);
            $game->refresh()->setRelations([]);
            $lastStep = $game->season_transition_step ?? -1;
        }

        // Not done yet: more processors remain.
        if ($lastStep < $totalSteps - 1) {
            return $this->progress($game, $totalSteps);
        }

        $this->finalize($game, $data);

        return ['done' => true, 'step' => $totalSteps - 1, 'totalSteps' => $totalSteps];
    }

    private function progress(Game $game, int $totalSteps): array
    {
        return [
            'done' => false,
            'step' => $game->season_transition_step,
            'totalSteps' => $totalSteps,
        ];
    }

    private function restoreTransitionData(Game $game): ?SeasonTransitionData
    {
        $stored = $game->season_transition_data;

        if ($stored === null) {
            return null;
        }

        return new SeasonTransitionData(
            oldSeason: $stored['oldSeason'] ?? '',
            newSeason: $stored['newSeason'] ?? '',
            competitionId: $stored['competitionId'] ?? $game->competition_id,
            playerChanges: $stored['playerChanges'] ?? [],
            metadata: $stored['metadata'] ?? [],
        );
    }

    private function finalize(Game $game, SeasonTransitionData $data): void
    {
        $excludeKeys = [
            'loanReturns', 'preContractTransfers', 'agreedTransfers',
            'contractRenewals', 'retiredPlayers', 'retirementAnnouncements',
            'squadReplenishment', 'freeAgentSignings',
        ];
        SeasonArchive::where('game_id', $game->id)
            ->where('season', $data->oldSeason)
            ->update(['transition_log' => json_encode([
                'oldSeason' => $data->oldSeason,
                'newSeason' => $data->newSeason,
                'competitionId' => $data->competitionId,
                'metadata' => array_diff_key($data->metadata, array_flip($excludeKeys)),
            ])]);

        $game->refresh()->setRelations([]);

        if ($game->preseason_opponents_pending) {
            $currentDate = $game->current_date;
        } else {
            $firstMatch = $game->getFirstCompetitiveMatch();
            $fallbackDate = ((int) $game->season) . '-08-15';
            $currentDate = $firstMatch?->scheduled_date ?? $fallbackDate;
        }

        $game->update([
            'current_date' => $currentDate,
            'season_transitioning_at' => null,
            'season_transition_step' => null,
            'season_transition_data' => null,
        ]);

        event(new SeasonStarted($game));
    }
}
