<?php

namespace Tests\Unit\Match;

use App\Modules\Match\Services\MatchSimulator;
use Tests\TestCase;

/**
 * Qualifying playoff upset factor: the two-legged knockout compresses the
 * strength gap so underdogs have a real chance (Juventus CAN go out and
 * drop to the Europa Cup) while favourites remain favourites.
 */
class QualifyingUpsetTest extends TestCase
{
    private function apply(float $home, float $away): array
    {
        $sim = (new \ReflectionClass(MatchSimulator::class))->newInstanceWithoutConstructor();
        $m = new \ReflectionMethod($sim, 'applyQualifyingUpset');
        $m->setAccessible(true);

        return $m->invoke($sim, $home, $away);
    }

    public function test_gap_compresses_but_favourite_stays_favourite(): void
    {
        [$home, $away] = $this->apply(85.0, 65.0);

        // 25% toward the mean (75): 85→82.5, 65→67.5
        $this->assertEqualsWithDelta(82.5, $home, 0.01);
        $this->assertEqualsWithDelta(67.5, $away, 0.01);
        $this->assertGreaterThan($away, $home);
        // Gap shrank from 20 to 15
        $this->assertEqualsWithDelta(15.0, $home - $away, 0.01);
    }

    public function test_equal_teams_unchanged(): void
    {
        [$home, $away] = $this->apply(75.0, 75.0);

        $this->assertEqualsWithDelta(75.0, $home, 0.01);
        $this->assertEqualsWithDelta(75.0, $away, 0.01);
    }

    public function test_underdog_home_gets_boost(): void
    {
        // Underdog at home: gap closes, home disadvantage shrinks
        [$home, $away] = $this->apply(65.0, 85.0);

        $this->assertEqualsWithDelta(67.5, $home, 0.01);
        $this->assertEqualsWithDelta(82.5, $away, 0.01);
    }

    public function test_never_reverses_the_tie(): void
    {
        // Even a huge gap only compresses — it never flips who is stronger.
        [$home, $away] = $this->apply(95.0, 55.0);

        $this->assertGreaterThan($away, $home);
    }
}
