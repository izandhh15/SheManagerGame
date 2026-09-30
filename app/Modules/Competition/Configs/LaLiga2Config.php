<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

class LaLiga2Config implements CompetitionConfig, HasSeasonGoals
{
    /**
     * Primera Federación TV revenue by position (in cents). 14 teams, ~€1M
     * total pool (SheManagerGame rescale of the ~€100M Segunda pool ÷100).
     */
    private const TV_REVENUE = [
        1 => 11_000_000,      // €110K
        2 => 10_000_000,      // €100K
        3 => 9_000_000,       // €90K
        4 => 8_500_000,       // €85K
        5 => 8_000_000,       // €80K
        6 => 7_500_000,       // €75K
        7 => 7_000_000,       // €70K
        8 => 6_500_000,       // €65K
        9 => 6_000_000,       // €60K
        10 => 5_500_000,      // €55K
        11 => 5_000_000,      // €50K
        12 => 4_750_000,      // €47.5K
        13 => 4_500_000,      // €45K
        14 => 4_500_000,      // €45K
    ];

    private const POSITION_FACTORS = [
        'top' => 1.05,        // 1st-6th (promotion zone)
        'mid_high' => 1.0,    // 7th-10th
        'mid_low' => 0.95,    // 11th-12th
        'relegation' => 0.85, // 13th-14th
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_PROMOTION => ['targetPosition' => 2, 'label' => 'game.goal_promotion'],
        Game::GOAL_PLAYOFF => ['targetPosition' => 6, 'label' => 'game.goal_playoff'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 7, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 13, 'label' => 'game.goal_survival'],
    ];

    /**
     * Map reputation to season goal.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_PLAYOFF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_SURVIVAL,
    ];

    public function getTvRevenue(int $position): int
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[14];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 6) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 10) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 12) {
            return self::POSITION_FACTORS['mid_low'];
        }
        return self::POSITION_FACTORS['relegation'];
    }

    public function getSeasonGoal(string $reputation): string
    {
        return self::REPUTATION_TO_GOAL[$reputation] ?? Game::GOAL_TOP_HALF;
    }

    public function getGoalTargetPosition(string $goal): int
    {
        return self::SEASON_GOALS[$goal]['targetPosition'] ?? 11;
    }

    public function getAvailableGoals(): array
    {
        return self::SEASON_GOALS;
    }

    public function getTopScorerAwardName(): string
    {
        return 'season.pichichi';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.zamora';
    }

    public function getKnockoutPrizeMoney(int $roundsFromFinal): int
    {
        return 0;
    }

    public function getLeaguePhaseQualificationBonus(int $position): int
    {
        return 0;
    }

    public function getStandingsZones(): array
    {
        $promotions = config('countries.ES.promotions', []);
        $rule = collect($promotions)->first(fn ($r) => $r['bottom_division'] === 'ESP2');

        $zones = [];

        // Zones reflect the *notional* (config-declared) positions a team
        // would occupy for direct promotion / playoff qualification. The
        // end-of-season allocator may shift the actual filling down when
        // reserves cluster at the top, but the standings UI shows the
        // pristine zone layout — that's what users expect to see.
        $directCount = (int) ($rule['direct_count'] ?? 0);
        $playoffCount = (int) ($rule['playoff_count'] ?? 0);

        if ($rule && $directCount > 0) {
            $zones[] = [
                'minPosition' => 1,
                'maxPosition' => $directCount,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.direct_promotion',
            ];
        }

        if ($rule && $playoffCount > 0) {
            $zones[] = [
                'minPosition' => $directCount + 1,
                'maxPosition' => $directCount + $playoffCount,
                'borderColor' => 'green-300',
                'bgColor' => 'bg-green-300',
                'label' => 'game.promotion_playoff',
            ];
        }

        $zones[] = [
            'minPosition' => 19,
            'maxPosition' => 22,
            'borderColor' => 'red-500',
            'bgColor' => 'bg-red-500',
            'label' => 'game.relegation',
        ];

        return $zones;
    }

}
