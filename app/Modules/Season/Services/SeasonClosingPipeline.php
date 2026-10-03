<?php

namespace App\Modules\Season\Services;

use App\Modules\Manager\Processors\SnapshotManagerSeasonRecordProcessor;
use App\Modules\Manager\Processors\TrophyRecordingProcessor;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\AgreedTransferCompletionProcessor;
use App\Modules\Season\Processors\AwardsGalaProcessor;
use App\Modules\Season\Processors\AIFreeAgentSigningProcessor;
use App\Modules\Season\Processors\AIReserveCallUpProcessor;
use App\Modules\Season\Processors\ClubWorldCupQualificationProcessor;
use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Season\Processors\ContractRenewalProcessor;
use App\Modules\Season\Processors\DomesticCupQualificationProcessor;
use App\Modules\Season\Processors\FinalizeOtherLeaguesProcessor;
use App\Modules\Season\Processors\LeaderboardStatsProcessor;
use App\Modules\Season\Processors\LoanReturnProcessor;
use App\Modules\Season\Processors\PlayerDevelopmentProcessor;
use App\Modules\Season\Processors\PlayerRetirementProcessor;
use App\Modules\Season\Processors\PreContractTransferProcessor;
use App\Modules\Season\Processors\PromotionRelegationProcessor;
use App\Modules\Season\Processors\ReputationUpdateProcessor;
use App\Modules\Season\Processors\ReserveOveragePromotionProcessor;
use App\Modules\Season\Processors\SeasonArchiveProcessor;
use App\Modules\Season\Processors\SeasonSettlementProcessor;
use App\Modules\Season\Processors\SeasonSimulationProcessor;
use App\Modules\Season\Processors\SquadReplenishmentProcessor;
use App\Modules\Season\Processors\StadiumLoanBillingProcessor;
use App\Modules\Season\Processors\StatsResetProcessor;
use App\Modules\Season\Processors\UserSquadCareerSnapshotProcessor;
use App\Modules\Season\Processors\SupercupQualificationProcessor;
use App\Modules\Season\Processors\AffiliateFirstTeamSackProcessor;
use App\Modules\Season\Processors\TransferMarketResetProcessor;
use App\Modules\Season\Processors\UefaQualificationProcessor;
use App\Modules\Season\Processors\YouthAcademyClosingProcessor;
use App\Modules\Stadium\Processors\FanLoyaltyUpdateProcessor;
use App\Models\Game;
use App\Support\QueryProfiler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closes the old season: archiving, settlements, contracts, retirements,
 * promotions/relegations, and qualification for next season's competitions.
 */
class SeasonClosingPipeline
{
    /** @var SeasonProcessor[] */
    private array $processors = [];

    public function __construct(
        ReserveOveragePromotionProcessor $reserveOveragePromotion,
        LoanReturnProcessor $loanReturn,
        AIReserveCallUpProcessor $aiReserveCallUp,
        TrophyRecordingProcessor $trophyRecording,
        LeaderboardStatsProcessor $leaderboardStats,
        SnapshotManagerSeasonRecordProcessor $snapshotManagerSeasonRecord,
        ContractExpirationProcessor $contractExpiration,
        SeasonArchiveProcessor $seasonArchive,
        PreContractTransferProcessor $preContractTransfer,
        AgreedTransferCompletionProcessor $agreedTransferCompletion,
        ContractRenewalProcessor $contractRenewal,
        PlayerRetirementProcessor $playerRetirement,
        SquadReplenishmentProcessor $squadReplenishment,
        AIFreeAgentSigningProcessor $aiFreeAgentSigning,
        PlayerDevelopmentProcessor $playerDevelopment,
        SeasonSettlementProcessor $seasonSettlement,
        StadiumLoanBillingProcessor $stadiumLoanBilling,
        StatsResetProcessor $statsReset,
        UserSquadCareerSnapshotProcessor $userSquadCareerSnapshot,
        TransferMarketResetProcessor $transferMarketReset,
        FinalizeOtherLeaguesProcessor $finalizeOtherLeagues,
        SeasonSimulationProcessor $seasonSimulation,
        SupercupQualificationProcessor $supercupQualification,
        DomesticCupQualificationProcessor $domesticCupQualification,
        PromotionRelegationProcessor $promotionRelegation,
        ReputationUpdateProcessor $reputationUpdate,
        FanLoyaltyUpdateProcessor $fanLoyaltyUpdate,
        YouthAcademyClosingProcessor $youthAcademyClosing,
        UefaQualificationProcessor $uefaQualification,
        ClubWorldCupQualificationProcessor $clubWorldCupQualification,
        AffiliateFirstTeamSackProcessor $affiliateFirstTeamSack,
        AwardsGalaProcessor $awardsGala,
    ) {
        $this->processors = [
            $reserveOveragePromotion,
            $loanReturn,
            $aiReserveCallUp,
            $trophyRecording,
            $leaderboardStats,
            $snapshotManagerSeasonRecord,
            $contractExpiration,
            $seasonArchive,
            $preContractTransfer,
            $agreedTransferCompletion,
            $contractRenewal,
            $playerRetirement,
            $squadReplenishment,
            $aiFreeAgentSigning,
            $playerDevelopment,
            $seasonSettlement,
            $stadiumLoanBilling,
            $userSquadCareerSnapshot,
            $statsReset,
            $transferMarketReset,
            $finalizeOtherLeagues,
            $seasonSimulation,
            $supercupQualification,
            $domesticCupQualification,
            $promotionRelegation,
            $reputationUpdate,
            $fanLoyaltyUpdate,
            $youthAcademyClosing,
            $uefaQualification,
            $clubWorldCupQualification,
            $affiliateFirstTeamSack,
            $awardsGala,
        ];

        usort($this->processors, fn ($a, $b) => $a->priority() <=> $b->priority());
    }

    /**
     * Close the old season and return transition data for setup.
     *
     * @param  int  $startFromStep  Global step index to resume from (skip steps <= this value)
     * @param  SeasonTransitionData|null  $existingData  Restored DTO from a previous checkpoint
     * @param  float|null  $maxSeconds  Time budget; break early when exceeded (checkpoint already saved, safe to resume)
     */
    public function run(Game $game, int $startFromStep = -1, ?SeasonTransitionData $existingData = null, ?float $maxSeconds = null): SeasonTransitionData
    {
        $chunkStart = microtime(true);
        $oldSeason = $game->season;
        $newSeason = $this->incrementSeason($oldSeason);

        $data = $existingData ?? new SeasonTransitionData(
            oldSeason: $oldSeason,
            newSeason: $newSeason,
            competitionId: $game->competition_id,
        );

        foreach ($this->processors as $index => $processor) {
            if ($index <= $startFromStep) {
                continue;
            }

            $processorName = class_basename($processor);
            $profile = QueryProfiler::start();

            try {
                // R1: the checkpoint is written INSIDE the processor's
                // transaction. The old code committed the processor first and
                // wrote the checkpoint in a separate query afterwards; if the
                // process died in between (Wasmer Edge is unstable and the
                // code itself documents killed connections/timeouts), the next
                // chunk re-ran the processor and its non-idempotent effects
                // were applied twice — ContractExpirationProcessor's stale
                // free-agent cleanup even DELETED the free agents created by
                // the first pass (permanent loss of real players). Now the
                // step and the DTO persist only together with the processor's
                // work: a crash either rolls everything back (the step is
                // safely re-run from a clean slate) or commits everything
                // (the step is safely skipped on resume).
                $data = DB::transaction(function () use ($processor, $game, $data, $index) {
                    $result = $processor->process($game, $data);

                    $game->updateQuietly([
                        'season_transition_step' => $index,
                        'season_transition_data' => $result,
                    ]);

                    return $result;
                });
            } catch (\Throwable $e) {
                Log::error('Season closing processor failed', [
                    'processor' => get_class($processor),
                    'step' => $index,
                    'game_id' => $game->id,
                    'user_id' => $game->user_id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $stats = $profile->snapshot();
            Log::info(
                "[SeasonClosing {$game->id}] {$processorName} (priority {$processor->priority()}) completed in {$stats['wall_ms']}ms",
                $stats,
            );

            // Time-boxed execution: stop here if the budget is spent. The
            // checkpoint written inside the processor's transaction above
            // makes it safe to resume in a later chunk.
            if ($maxSeconds !== null && (microtime(true) - $chunkStart) >= $maxSeconds) {
                break;
            }
        }

        return $data;
    }

    /**
     * Increment the season year.
     */
    private function incrementSeason(string $season): string
    {
        if (str_contains($season, '-')) {
            $parts = explode('-', $season);
            $startYear = (int) $parts[0] + 1;
            $endYear = (int) $parts[1] + 1;

            return $startYear.'-'.str_pad((string) $endYear, 2, '0', STR_PAD_LEFT);
        }

        return (string) ((int) $season + 1);
    }

    /**
     * @return SeasonProcessor[]
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }
}
