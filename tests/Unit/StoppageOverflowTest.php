<?php

namespace Tests\Unit;

use App\Modules\Match\Support\StoppageCalculator;
use Tests\TestCase;

/**
 * BAJA review: StoppageCalculator::calculateForHalf() computed the
 * second-half overflow as (latest_raw_minute - 90), but second-half raw
 * minutes already include the first-half stoppage (a 90+N' event sits at
 * absolute 90 + fhs + N). The overflow must subtract fhs, otherwise the
 * 2nd-half stoppage — and the "90+N'" display — is inflated by the 1H
 * stoppage.
 */
class StoppageOverflowTest extends TestCase
{
    public function test_second_half_overflow_subtracts_first_half_stoppage(): void
    {
        $calc = new StoppageCalculator();

        // 1H: one goal at 44' → formula ceil(60/60)=1, overflow 0 → fhs = 1.
        // 2H: one assist at raw 93 == display 90+2' with fhs=1.
        $result = $calc->calculateRegulation([
            ['minute' => 44, 'event_type' => 'goal'],
            ['minute' => 93, 'event_type' => 'assist'],
        ]);

        $this->assertSame(1, $result['first_half']);
        // Without the fhs correction the overflow would be 93-90 = 3.
        $this->assertSame(2, $result['second_half']);
    }

    public function test_first_half_is_formula_driven_without_overflow(): void
    {
        $calc = new StoppageCalculator();

        $result = $calc->calculateRegulation([
            ['minute' => 44, 'event_type' => 'goal'],
        ]);

        // formula ceil(60/60)=1, overflow max(0, 44-45)=0 → 1 (no fhs involved).
        $this->assertSame(1, $result['first_half']);
    }
}
