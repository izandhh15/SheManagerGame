<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

class NWSLConfig implements CompetitionConfig, HasSeasonGoals
{
    private const TV_REVENUE = [
        1 => 800_000_000,     // €1M
        2 => 720_000_000,
        3 => 640_000_000,
        4 => 70_000_000,
        5 => 65_000_000,
        6 => 60_000_000,
        7 => 55_000_000,
        8 => 50_000_000,
        9 => 45_000_000,
        10 => 40_000_000,
        11 => 38_000_000,
        12 => 36_000_000,
        13 => 34_000_000,
        14 => 32_000_000,
        15 => 30_000_000,
        16 => 28_000_000,
    ];

    private const POSITION_FACTORS = [
        'top' => 1.10,
        'mid_high' => 1.0,
        'mid_low' => 0.95,
        'relegation' => 0.85,
    ];

    private const SEASON_GOALS = [
        Game::GOAL_TITLE => ['targetPosition' => 1, 'label' => 'game.goal_title'],
        Game::GOAL_EUROPA_LEAGUE => ['targetPosition' => 3, 'label' => 'game.goal_europa_league'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 8, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 14, 'label' => 'game.goal_survival'],
    ];

    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_EUROPA_LEAGUE,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_SURVIVAL,
    ];

    public function getTvRevenue(int $position): int|float
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[16];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 4) return self::POSITION_FACTORS['top'];
        if ($position <= 8) return self::POSITION_FACTORS['mid_high'];
        if ($position <= 12) return self::POSITION_FACTORS['mid_low'];
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
        return 'season.top_scorer_american';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_american';
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
        return [
            [
                'minPosition' => 15,
                'maxPosition' => 16,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.relegation',
            ],
        ];
    }
}
