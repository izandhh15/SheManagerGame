<?php

namespace Tests\Unit;

use App\Modules\Competition\Configs\ChampionsLeagueConfig;
use App\Modules\Competition\Configs\EuropaLeagueConfig;
use Tests\TestCase;

/**
 * BAJA review: ChampionsLeagueConfig modeled the men's 36-team Swiss format
 * (TV table 1–36, zones 25–36, fallback TV_REVENUE[36]), but data/2026
 * ships 28 UCL teams. The config must match the real data.
 *
 * The Europa Cup is a pure knockout since 2026-27 (no league phase): no TV
 * ranking money, no standings zones, no position factor, and prize money
 * for its four knockout rounds (R16 €70K, QF €70K, SF €75K, champion €80K).
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

    public function test_uel_has_no_tv_revenue_without_league_phase(): void
    {
        $config = new EuropaLeagueConfig();

        $this->assertSame(0, (int) $config->getTvRevenue(1));
        $this->assertSame(0, (int) $config->getTvRevenue(16));
    }

    public function test_uel_knockout_prize_money_covers_four_rounds(): void
    {
        $config = new EuropaLeagueConfig();

        // Keyed by rounds remaining after the one won: 0 is the final.
        $this->assertSame(8_000_000, $config->getKnockoutPrizeMoney(0)); // champion
        $this->assertSame(7_500_000, $config->getKnockoutPrizeMoney(1)); // reach final
        $this->assertSame(7_500_000, $config->getKnockoutPrizeMoney(2)); // reach SF
        $this->assertSame(7_000_000, $config->getKnockoutPrizeMoney(3)); // reach QF
        $this->assertSame(0, $config->getKnockoutPrizeMoney(4)); // no fifth round
    }

    public function test_uel_has_flat_position_factor_and_no_league_phase_bonus_or_zones(): void
    {
        $config = new EuropaLeagueConfig();

        $this->assertSame(1.0, $config->getPositionFactor(1));
        $this->assertSame(1.0, $config->getPositionFactor(16));
        $this->assertSame(0, $config->getLeaguePhaseQualificationBonus(1));
        $this->assertSame([], $config->getStandingsZones());
    }
}
