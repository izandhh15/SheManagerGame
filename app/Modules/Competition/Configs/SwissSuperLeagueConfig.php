<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

class SwissSuperLeagueConfig implements CompetitionConfig, HasSeasonGoals
{
    /**
     * AXA Women's Super League TV revenue by position (in cents).
     *
     * A small collective deal shared very evenly: the champions earn barely
     * twice the bottom club.
     */
    private const TV_REVENUE = [
        1 => 400_000_000,     // €4M
        2 => 360_000_000,     // €3.6M
        3 => 330_000_000,     // €3.3M
        4 => 300_000_000,     // €3M
        5 => 280_000_000,     // €2.8M
        6 => 260_000_000,     // €2.6M
        7 => 240_000_000,     // €2.4M
        8 => 220_000_000,     // €2.2M
        9 => 200_000_000,     // €2M
        10 => 190_000_000,    // €1.9M
    ];

    private const POSITION_FACTORS = [
        'top' => 1.10,        // 1st-3rd
        'mid_high' => 1.0,    // 4th-6th
        'mid_low' => 0.95,    // 7th-8th
        'relegation' => 0.85, // 9th-10th
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_TITLE => ['targetPosition' => 1, 'label' => 'game.goal_title'],
        Game::GOAL_EUROPA_LEAGUE => ['targetPosition' => 3, 'label' => 'game.goal_europa_league'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 5, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 8, 'label' => 'game.goal_survival'],
    ];

    /**
     * Map reputation to season goal.
     *
     * Reputation is a Europe-wide scale and this league has no elite club, so
     * the map is shifted one rung relative to the big-five leagues: the
     * continental sides here are the domestic giants and are expected to win
     * the title, not merely to qualify for Europe.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_EUROPA_LEAGUE,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_SURVIVAL,
    ];

    public function getTvRevenue(int $position): int|float
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[10];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 3) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 6) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 8) {
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
        return self::SEASON_GOALS[$goal]['targetPosition'] ?? 5;
    }

    public function getAvailableGoals(): array
    {
        return self::SEASON_GOALS;
    }

    public function getTopScorerAwardName(): string
    {
        return 'season.top_scorer_swiss';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_swiss';
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
        $slots = config('countries.CH.continental_slots.SUI1', []);

        $zones = [];

        if (!empty($slots['UCL'])) {
            $zones[] = [
                'minPosition' => min($slots['UCL']),
                'maxPosition' => max($slots['UCL']),
                'borderColor' => 'blue-500',
                'bgColor' => 'bg-blue-500',
                'label' => 'game.champions_league',
            ];
        }

        if (!empty($slots['UEL'])) {
            $zones[] = [
                'minPosition' => min($slots['UEL']),
                'maxPosition' => max($slots['UEL']),
                'borderColor' => 'orange-500',
                'bgColor' => 'bg-orange-500',
                'label' => 'game.europa_league',
            ];
        }

        // No relegation zone: the Women's Super League has no lower division in the game,
        // so no relegation is modeled (see config/countries.php promotions).
        return $zones;
    }
}
