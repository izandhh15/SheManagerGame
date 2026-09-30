<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

class SecondeLigueConfig implements CompetitionConfig, HasSeasonGoals
{
    /**
     * Seconde Ligue TV revenue by position (in cents). 11 teams.
     */
    private const TV_REVENUE = [
        1 => 50_000_000,      // €500K
        2 => 45_000_000,      // €450K
        3 => 41_000_000,      // €410K
        4 => 38_000_000,      // €380K
        5 => 35_000_000,      // €350K
        6 => 32_000_000,      // €320K
        7 => 29_000_000,      // €290K
        8 => 26_000_000,      // €260K
        9 => 23_000_000,      // €230K
        10 => 21_000_000,     // €210K
        11 => 20_000_000,     // €200K
    ];

    private const POSITION_FACTORS = [
        'top' => 1.05,        // 1st-4th (promotion zone)
        'mid_high' => 1.0,    // 5th-7th
        'mid_low' => 0.95,    // 8th-9th
        'relegation' => 0.85, // 10th-11th
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_PROMOTION => ['targetPosition' => 2, 'label' => 'game.goal_promotion'],
        Game::GOAL_PLAYOFF => ['targetPosition' => 4, 'label' => 'game.goal_playoff'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 6, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 9, 'label' => 'game.goal_survival'],
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

    public function getTvRevenue(int $position): int|float
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[11];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 4) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 7) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 9) {
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
        return self::SEASON_GOALS[$goal]['targetPosition'] ?? 8;
    }

    public function getAvailableGoals(): array
    {
        return self::SEASON_GOALS;
    }

    public function getTopScorerAwardName(): string
    {
        return 'season.top_scorer_fra2';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_fra2';
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
        $promotions = config('countries.FR.promotions', []);
        $rule = collect($promotions)->first(fn ($r) => $r['bottom_division'] === 'FRA2');

        $zones = [];

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
            'minPosition' => 10,
            'maxPosition' => 11,
            'borderColor' => 'red-500',
            'bgColor' => 'bg-red-500',
            'label' => 'game.relegation',
        ];

        return $zones;
    }
}
