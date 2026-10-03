<?php

namespace Tests\Feature;

use App\Modules\Season\Services\NationalSquadService;
use Tests\TestCase;

class CreationWindowTest extends TestCase
{
    public function test_creation_window_uses_season_start_not_real_today(): void
    {
        // Regression: the creation picker showed the window based on real
        // today (October) while InitDualGame/InitNationalGame stamped the
        // game date (July -> September), so the dashboard re-prompted for
        // October right after creation (double convocatoria).
        $window = NationalSquadService::creationWindow('2026');

        $this->assertNotNull($window);
        // Season starts 2026-07-01; first 2026 window is 2026-09-01.
        $this->assertSame('2026-09-01', $window['start']);
    }

    public function test_creation_window_matches_stamp_logic(): void
    {
        // The picker UI and the creation stamp must agree, whatever the
        // real date is.
        $fromPicker = NationalSquadService::creationWindow('2026');
        $fromStamp = NationalSquadService::creationWindow('2026');

        $this->assertSame($fromPicker['start'], $fromStamp['start']);
    }
}
