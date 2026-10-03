<?php

namespace Tests\Unit;

use App\Modules\Season\Processors\AffiliateFirstTeamSackProcessor;
use App\Modules\Season\Processors\AgreedTransferCompletionProcessor;
use App\Modules\Season\Processors\BudgetProjectionProcessor;
use App\Modules\Season\Processors\ClubWorldCupInitProcessor;
use App\Modules\Season\Processors\ClubWorldCupQualificationProcessor;
use App\Modules\Season\Processors\ContractRenewalProcessor;
use App\Modules\Season\Processors\SeasonSettlementProcessor;
use App\Modules\Season\Processors\StadiumLoanBillingProcessor;
use App\Modules\Season\Processors\StatsResetProcessor;
use App\Modules\Season\Processors\UserSquadCareerSnapshotProcessor;
use App\Modules\Season\Services\SeasonClosingPipeline;
use App\Modules\Season\Services\SeasonSetupPipeline;
use Tests\TestCase;

/**
 * BAJA review: both season pipelines sorted processors with a bare
 * usort() on priority(). usort() is not guaranteed stable, so processors
 * sharing a priority (35, 60, 65, 101 in closing; 107 in setup) could run
 * in an arbitrary relative order. Ties now break by the original
 * registration order.
 */
class PipelineProcessorOrderTest extends TestCase
{
    /**
     * @return list<object>
     */
    private function processors(object $pipeline): array
    {
        $prop = new \ReflectionProperty($pipeline, 'processors');
        $prop->setAccessible(true);

        return $prop->getValue($pipeline);
    }

    /**
     * @param  list<object>  $processors
     * @return list<string>
     */
    private function classNames(array $processors): array
    {
        return array_map(fn ($p) => $p::class, $processors);
    }

    public function test_closing_pipeline_priorities_are_non_decreasing(): void
    {
        $processors = $this->processors(app(SeasonClosingPipeline::class));
        $priorities = array_map(fn ($p) => $p->priority(), $processors);

        $sorted = $priorities;
        sort($sorted);

        $this->assertSame($sorted, $priorities);
    }

    public function test_setup_pipeline_priorities_are_non_decreasing(): void
    {
        $processors = $this->processors(app(SeasonSetupPipeline::class));
        $priorities = array_map(fn ($p) => $p->priority(), $processors);

        $sorted = $priorities;
        sort($sorted);

        $this->assertSame($sorted, $priorities);
    }

    /**
     * Tied priorities keep the constructor's registration order.
     */
    public function test_closing_pipeline_breaks_priority_ties_by_registration_order(): void
    {
        $names = $this->classNames($this->processors(app(SeasonClosingPipeline::class)));
        $indexOf = fn (string $class) => array_search($class, $names, true);

        // Priority 35
        $this->assertLessThan(
            $indexOf(ContractRenewalProcessor::class),
            $indexOf(AgreedTransferCompletionProcessor::class),
        );
        // Priority 60
        $this->assertLessThan(
            $indexOf(UserSquadCareerSnapshotProcessor::class),
            $indexOf(SeasonSettlementProcessor::class),
        );
        // Priority 65
        $this->assertLessThan(
            $indexOf(StatsResetProcessor::class),
            $indexOf(StadiumLoanBillingProcessor::class),
        );
        // Priority 101
        $this->assertLessThan(
            $indexOf(AffiliateFirstTeamSackProcessor::class),
            $indexOf(ClubWorldCupQualificationProcessor::class),
        );
    }

    public function test_setup_pipeline_breaks_priority_ties_by_registration_order(): void
    {
        $names = $this->classNames($this->processors(app(SeasonSetupPipeline::class)));
        $indexOf = fn (string $class) => array_search($class, $names, true);

        // Priority 107
        $this->assertLessThan(
            $indexOf(ClubWorldCupInitProcessor::class),
            $indexOf(BudgetProjectionProcessor::class),
        );
    }
}
