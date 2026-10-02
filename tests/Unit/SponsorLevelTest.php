<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Modules\Commercial\Services\SponsorLevelService;
use Tests\TestCase;

class SponsorLevelTest extends TestCase
{
    public function test_elite_clubs_attract_international(): void
    {
        $service = new SponsorLevelService;

        $levels = $service->eligibleLevels(ClubProfile::REPUTATION_ELITE);

        $this->assertContains(SponsorLevelService::LEVEL_INTERNATIONAL, $levels);
        $this->assertNotContains(SponsorLevelService::LEVEL_LOCAL, $levels);
        $this->assertSame(SponsorLevelService::LEVEL_INTERNATIONAL, $service->topLevel(ClubProfile::REPUTATION_ELITE));
    }

    public function test_continental_clubs_attract_international(): void
    {
        $service = new SponsorLevelService;

        $this->assertContains(
            SponsorLevelService::LEVEL_INTERNATIONAL,
            $service->eligibleLevels(ClubProfile::REPUTATION_CONTINENTAL)
        );
    }

    public function test_local_clubs_only_get_local_and_regional(): void
    {
        $service = new SponsorLevelService;

        $levels = $service->eligibleLevels(ClubProfile::REPUTATION_LOCAL);

        $this->assertSame(
            [SponsorLevelService::LEVEL_REGIONAL, SponsorLevelService::LEVEL_LOCAL],
            $levels
        );
        $this->assertNotContains(SponsorLevelService::LEVEL_INTERNATIONAL, $levels);
        $this->assertNotContains(SponsorLevelService::LEVEL_NATIONAL, $levels);
    }

    public function test_modest_clubs_get_up_to_national(): void
    {
        $service = new SponsorLevelService;

        $levels = $service->eligibleLevels(ClubProfile::REPUTATION_MODEST);

        $this->assertContains(SponsorLevelService::LEVEL_NATIONAL, $levels);
        $this->assertNotContains(SponsorLevelService::LEVEL_INTERNATIONAL, $levels);
    }
}
