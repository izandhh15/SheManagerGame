<?php

namespace App\Modules\Season\Services;

use App\Modules\Manager\Processors\ApplyPendingTeamSwitchProcessor;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\BudgetProjectionProcessor;
use App\Modules\Season\Processors\ClubWorldCupInitProcessor;
use App\Modules\Season\Processors\ContinentalAndCupInitProcessor;
use App\Modules\Season\Processors\DefaultInvestmentProcessor;
use App\Modules\Season\Processors\GenerateNamingRightsOffersProcessor;
use App\Modules\Season\Processors\GenerateSponsorOffersProcessor;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Processors\NewSeasonResetProcessor;
use App\Modules\Season\Processors\PreSeasonFixtureProcessor;
use App\Modules\Season\Processors\SeasonTicketDefaultsProcessor;
use App\Modules\Season\Processors\SeedInitialNamingDealProcessor;
use App\Modules\Season\Processors\SquadRegistrationEnforcementProcessor;
use App\Modules\Season\Processors\StandingsResetProcessor;
use App\Modules\Season\Processors\TransferMarketSeedProcessor;
use App\Modules\Season\Processors\YouthAcademyPromotionProcessor;
use App\Models\Game;
use App\Support\QueryProfiler;
use Illuminate\Support\Facades\Log;

/**
 * Sets up the new season: fixtures, standings, budgets, competitions,
 * and new-season setup. Used by both new game creation and season transitions.
 */
class SeasonSetupPipeline
{
    /** @var SeasonProcessor[] */
    private array $processors = [];

    public function __construct(
        ApplyPendingTeamSwitchProcessor $applyPendingTeamSwitch,
        YouthAcademyPromotionProcessor $academyPromotion,
        LeagueFixtureProcessor $fixtureGeneration,
        StandingsResetProcessor $standingsReset,
        BudgetProjectionProcessor $budgetProjection,
        DefaultInvestmentProcessor $defaultInvestment,
        ContinentalAndCupInitProcessor $competitionInitialization,
        ClubWorldCupInitProcessor $clubWorldCupInit,
        SquadRegistrationEnforcementProcessor $squadRegistration,
        PreSeasonFixtureProcessor $preSeasonFixture,
        NewSeasonResetProcessor $newSeasonReset,
        TransferMarketSeedProcessor $transferMarketSeed,
        SeasonTicketDefaultsProcessor $seasonTicketDefaults,
        SeedInitialNamingDealProcessor $seedInitialNamingDeal,
        GenerateNamingRightsOffersProcessor $namingRightsOffers,
        GenerateSponsorOffersProcessor $sponsorOffers,
    ) {
        $this->processors = [
            $applyPendingTeamSwitch,
            $academyPromotion,
            $fixtureGeneration,
            $standingsReset,
            $budgetProjection,
            $defaultInvestment,
            $competitionInitialization,
            $clubWorldCupInit,
            // UEFA Super Cup does not exist in women's football: the
            // UefaSuperCupQualificationProcessor stays out of the pipeline
            // (its class is kept for the existing test coverage).
            $squadRegistration,
            $preSeasonFixture,
            $newSeasonReset,
            $transferMarketSeed,
            $seasonTicketDefaults,
            $seedInitialNamingDeal,
            $namingRightsOffers,
            $sponsorOffers,
        ];

        // usort() is not guaranteed stable: break priority ties by the original
        // registration order so the pipeline order is fully deterministic
        // (several processors share priorities, e.g. 107).
        $processors = $this->processors;
        $order = array_keys($processors);
        usort($order, fn ($i, $j) =>
            [$processors[$i]->priority(), $i] <=> [$processors[$j]->priority(), $j]);
        $this->processors = array_map(fn ($i) => $processors[$i], $order);
    }

    /**
     * Set up the new season using pre-built transition data.
     *
     * @param  int  $stepOffset  Global step offset (closing pipeline processor count)
     * @param  int  $startFromStep  Global step index to resume from (skip steps <= this value)
     * @param  float|null  $maxSeconds  Time budget; break early when exceeded (checkpoint already saved, safe to resume)
     */
    public function run(Game $game, SeasonTransitionData $data, int $stepOffset = 0, int $startFromStep = -1, ?float $maxSeconds = null): SeasonTransitionData
    {
        $chunkStart = microtime(true);
        foreach ($this->processors as $index => $processor) {
            $globalStep = $stepOffset + $index;

            if ($globalStep <= $startFromStep) {
                continue;
            }

            $processorName = class_basename($processor);
            $profile = QueryProfiler::start();

            try {
                // SIN transacción por procesador (24-10-2026, Neon pooler):
                // envolver cada procesador en DB::transaction abortaba la
                // transacción en silencio y el setup moría con SQLSTATE
                // [25P02] (visto en LeagueFixtureProcessor: el DELETE de
                // game_matches fallaba porque la transacción ya estaba
                // abortada tras el DELETE de match_attendances), dejando
                // la partida en "preparando" y 500 en /new-season en cada
                // reintento. En autocommit, un fallo se ve con su error
                // real y el checkpoint se escribe solo si el procesador
                // completó: los procesadores de setup son re-ejecutables
                // (empiezan borrando o con upsert), así que un reintento
                // tras un fallo no duplica efectos. Se pierde la
                // atomicidad R1 a cambio de que el setup funcione contra
                // el pooler; es el mismo patrón aplicado a
                // RefreshPlayerTemplates y a copyCompetitionTeamsToGame.
                $result = $processor->process($game, $data);

                $game->updateQuietly([
                    'season_transition_step' => $globalStep,
                    'season_transition_data' => $result,
                ]);

                $data = $result;
            } catch (\Throwable $e) {
                Log::error('Season setup processor failed', [
                    'processor' => get_class($processor),
                    'step' => $globalStep,
                    'game_id' => $game->id,
                    'user_id' => $game->user_id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $stats = $profile->snapshot();
            Log::info(
                "[SeasonSetup {$game->id}] {$processorName} (priority {$processor->priority()}) completed in {$stats['wall_ms']}ms",
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
     * @return SeasonProcessor[]
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }
}
