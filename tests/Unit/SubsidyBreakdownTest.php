<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Modules\Finance\Services\SubsidyBreakdownService;
use Tests\TestCase;

class SubsidyBreakdownTest extends TestCase
{
    public function test_valencian_club_splits_three_ways(): void
    {
        $service = new SubsidyBreakdownService;
        $team = Team::factory()->create(['name' => 'Valencia CF Femenino', 'country' => 'ES']);

        $breakdown = $service->breakdown(1000000, $team);

        $this->assertCount(3, $breakdown);
        $this->assertSame('Gobierno de España', $breakdown[0]['source']);
        $this->assertSame('Generalitat Valenciana', $breakdown[1]['source']);
        $this->assertSame(600000, $breakdown[0]['amount']);
        $this->assertSame(250000, $breakdown[1]['amount']);
        // Sums to the total (rounding absorbed by the last line).
        $this->assertSame(1000000, array_sum(array_column($breakdown, 'amount')));
    }

    public function test_catalan_club_uses_generalitat_de_catalunya(): void
    {
        $service = new SubsidyBreakdownService;
        $team = Team::factory()->create(['name' => 'FC Barcelona Femení', 'country' => 'ES']);

        $breakdown = $service->breakdown(500000, $team);

        $this->assertSame('Generalitat de Catalunya', $breakdown[1]['source']);
    }

    public function test_club_without_regional_mapping_gets_two_lines(): void
    {
        $service = new SubsidyBreakdownService;
        $team = Team::factory()->create(['name' => 'SD Logroñés', 'country' => 'ES']);

        $breakdown = $service->breakdown(200000, $team);

        $this->assertCount(2, $breakdown);
        $this->assertSame('Gobierno de España', $breakdown[0]['source']);
        $this->assertSame(200000, array_sum(array_column($breakdown, 'amount')));
    }

    public function test_zero_subsidy_returns_empty(): void
    {
        $service = new SubsidyBreakdownService;
        $team = Team::factory()->create(['name' => 'Valencia CF Femenino', 'country' => 'ES']);

        $this->assertSame([], $service->breakdown(0, $team));
    }
}
