<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Stadium\Services\DemandCurveService;
use Tests\TestCase;

/**
 * Fix 8: the attendance floor (absolute 500) must never exceed the stadium
 * capacity — tiny grounds reported attendance above their seat count.
 */
class DemandCurveFloorTest extends TestCase
{
    private function teamWithCapacity(int $capacity): Team
    {
        $team = new Team();
        $team->stadium_seats = $capacity;

        return $team;
    }

    private function reputation(string $level = ClubProfile::REPUTATION_MODEST, int $loyalty = 50): TeamReputation
    {
        $rep = new TeamReputation();
        $rep->reputation_level = $level;
        $rep->loyalty_points = $loyalty;

        return $rep;
    }

    private function competition(): Competition
    {
        $competition = new Competition();
        $competition->role = Competition::ROLE_LEAGUE;

        return $competition;
    }

    public function test_project_never_exceeds_capacity_in_tiny_ground(): void
    {
        $service = app(DemandCurveService::class);

        $attendance = $service->project(
            $this->teamWithCapacity(400),
            $this->reputation(),
            $this->reputation(),
            $this->competition(),
            400,
        );

        $this->assertLessThanOrEqual(400, $attendance);
    }

    public function test_project_baseline_never_exceeds_capacity_in_tiny_ground(): void
    {
        $service = app(DemandCurveService::class);

        $attendance = $service->projectBaseline(
            $this->teamWithCapacity(300),
            $this->reputation(),
            300,
        );

        $this->assertLessThanOrEqual(300, $attendance);
    }

    public function test_project_still_applies_floor_in_normal_ground(): void
    {
        $service = app(DemandCurveService::class);

        // Loyalty 0 → 50% fill of 20 000 = 10 000, above the 500 floor.
        $attendance = $service->project(
            $this->teamWithCapacity(20000),
            $this->reputation(loyalty: 0),
            $this->reputation(ClubProfile::REPUTATION_ELITE),
            $this->competition(),
            20000,
        );

        $this->assertLessThanOrEqual(20000, $attendance);
        $this->assertGreaterThanOrEqual(500, $attendance);
    }
}
