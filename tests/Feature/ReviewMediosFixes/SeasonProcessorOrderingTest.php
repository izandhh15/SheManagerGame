<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Modules\Season\Processors\AwardsGalaProcessor;
use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Season\Processors\SeasonArchiveProcessor;
use App\Modules\Season\Services\SeasonClosingPipeline;
use Tests\TestCase;

/**
 * Fix 10: the season-close ordering is archive (18) → gala (19) →
 * contract expiration (20), so players who leave on a free are archived
 * and award-eligible with the team they played for. The gala no longer
 * shares a priority with the expiration step (deterministic order).
 */
class SeasonProcessorOrderingTest extends TestCase
{
    public function test_archive_runs_before_gala_runs_before_expiration(): void
    {
        $archive = app(SeasonArchiveProcessor::class)->priority();
        $gala = app(AwardsGalaProcessor::class)->priority();
        $expiration = app(ContractExpirationProcessor::class)->priority();

        $this->assertLessThan($gala, $archive, 'archive must run before the gala');
        $this->assertLessThan($expiration, $gala, 'gala must run before contract expiration');
    }

    public function test_gala_priority_is_unique_in_closing_pipeline(): void
    {
        $order = app(SeasonClosingPipeline::class)->getProcessors();

        $galaPosition = null;
        $priorities = [];
        foreach ($order as $i => $processor) {
            $priorities[] = $processor->priority();
            if ($processor instanceof AwardsGalaProcessor) {
                $galaPosition = $i;
            }
        }

        $this->assertNotNull($galaPosition, 'gala must be wired into the closing pipeline');

        $galaPriority = $order[$galaPosition]->priority();
        $samePriority = array_filter(
            $order,
            fn ($p) => $p->priority() === $galaPriority && ! $p instanceof AwardsGalaProcessor
        );

        $this->assertCount(0, $samePriority, 'no other closing processor may share the gala priority');
    }

    public function test_expiration_still_runs_before_renewals(): void
    {
        // ContractExpiration (20) < ContractRenewalProcessor (35): the
        // reorder must not break the documented transfer chain.
        $this->assertLessThan(
            app(\App\Modules\Season\Processors\ContractRenewalProcessor::class)->priority(),
            app(ContractExpirationProcessor::class)->priority()
        );
    }
}
