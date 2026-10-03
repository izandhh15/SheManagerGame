<?php

namespace Tests\Unit;

use App\Modules\Competition\Configs\ChampionsLeagueConfig;
use App\Modules\Competition\Configs\EuropaLeagueConfig;
use Tests\TestCase;

/**
 * BAJA review: ChampionsLeagueConfig and EuropaLeagueConfig modeled the
 * men's 36-team Swiss format (TV table 1–36, zones 25–36, fallback
 * TV_REVENUE[36]), but data/2026 ships 28 UCL teams and 20 UEL teams.
 * The configs must match the real data.
 */
class ContinentalFormatTeamCountsTest extends TestCase
{
    public function test_ucl_tv_table_covers_exactly_28_teams(): void
    {
        $config = new ChampionsLeagueConfig();

        // €10K/position shape preserved: 86.5M at 1st … 59.5M at 28th.
        $this->assertSame(86_500_000, (int) $config->getTvRevenue(1));
        $this->assertSame(59_500_000, (int) $config->getTvRevenue(28));
        // Out-of-range falls back to the LAST real slot, not the old 36th.
        $this->assertSame(59_500_000, (int) $config->getTvRevenue(29));
        $this->assertSame(59_500_000, (int) $config->getTvRevenue(36));
    }

    public function test_ucl_zones_do_not_exceed_28_teams(): void
    {
        $zones = (new ChampionsLeagueConfig())->getStandingsZones();

        $max = max(array_column($zones, 'maxPosition'));
        $this->assertSame(28, $max, 'no zone may extend past the 28 real teams');

        $eliminated = array_values(array_filter($zones, fn (array $z) => $z['label'] === 'game.ucl_eliminated'));
        $this->assertCount(1, $eliminated);
        $this->assertSame(25, $eliminated[0]['minPosition']);
        $this->assertSame(28, $eliminated[0]['maxPosition']);
    }

    public function test_uel_tv_formula_spans_exactly_20_teams(): void
    {
        $config = new EuropaLeagueConfig();

        // €65K base + €1K/position (€85K at 1st … €66K at 20th).
        $this->assertSame(8_500_000, (int) $config->getTvRevenue(1));
        $this->assertSame(6_600_000, (int) $config->getTvRevenue(20));
    }

    public function test_uel_zones_do_not_exceed_20_teams(): void
    {
        $zones = (new EuropaLeagueConfig())->getStandingsZones();

        $max = max(array_column($zones, 'maxPosition'));
        $this->assertLessThanOrEqual(20, $max, 'no zone may extend past the 20 real teams');

        $playoff = array_values(array_filter($zones, fn (array $z) => $z['label'] === 'game.uel_knockout_playoff'));
        $this->assertCount(1, $playoff);
        // 20-team UEL: 1-12 direct to R16, 13-20 playoff (12 + 4 winners = 16).
        $this->assertSame(13, $playoff[0]['minPosition']);
        $this->assertSame(20, $playoff[0]['maxPosition']);
    }
}
