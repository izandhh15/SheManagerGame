<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

/**
 * Configuration for the UEFA Women's Nations League (WNL).
 *
 * Real format (2025 edition): League A with 4 groups of 4 teams, double
 * round-robin (6 matchdays). The group winner lifts the Nations League
 * trophy (gold standings zone); there is no relegation in v1.
 *
 * Groups (from groups.json):
 * - A1: Germany, Netherlands, Austria, Scotland
 * - A2: France, Iceland, Norway, Switzerland
 * - A3: Spain, England, Belgium, Portugal
 * - A4: Italy, Denmark, Sweden, Wales
 *
 * Season goals: elite and continental sides are expected to win the group
 * (GOAL_TITLE); everyone else targets a top-half finish.
 *
 * Prize money sits above World Cup qualifying (champion €12M vs WWCQ €8M),
 * reflecting UEFA's flagship national-team competition.
 *
 * All money values are well under 2^31 — the Wasmer PHP runtime is 32-bit.
 */
class WomensNationsLeagueConfig implements CompetitionConfig, HasSeasonGoals
{
    private const NUM_TEAMS = 4;

    /**
     * TV revenue by position, in cents. Champion earns €12M, double the
     * bottom side — a prestige payout above WWCQ levels.
     */
    private const TV_REVENUE = [
        1 => 1200_000_000,  // €12M
        2 => 1000_000_000,  // €10M
        3 => 850_000_000,   // €8.5M
        4 => 750_000_000,   // €7.5M
    ];

    private const POSITION_FACTORS = [
        'top' => 1.10,        // 1st (champion)
        'mid_high' => 1.0,    // 2nd
        'mid_low' => 0.95,    // 3rd
        'bottom' => 0.85,     // 4th
    ];

    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_TOP_HALF,
    ];

    public function getName(): string
    {
        return "UEFA Women's Nations League";
    }

    public function getTvRevenue(int $position): int|float
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[self::NUM_TEAMS];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position === 1) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position === 2) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position === 3) {
            return self::POSITION_FACTORS['mid_low'];
        }
        return self::POSITION_FACTORS['bottom'];
    }

    public function getSeasonGoal(string $reputation): string
    {
        return self::REPUTATION_TO_GOAL[$reputation] ?? Game::GOAL_TOP_HALF;
    }

    public function getGoalTargetPosition(string $goal): int
    {
        return match ($goal) {
            Game::GOAL_TITLE => 1,
            Game::GOAL_TOP_HALF => 2, // finish top half (top 2 of 4)
            Game::GOAL_SURVIVAL => 3,
            default => 2,
        };
    }

    public function getAvailableGoals(): array
    {
        return [
            Game::GOAL_TITLE => ['targetPosition' => $this->getGoalTargetPosition(Game::GOAL_TITLE), 'label' => 'game.goal_title'],
            Game::GOAL_TOP_HALF => ['targetPosition' => $this->getGoalTargetPosition(Game::GOAL_TOP_HALF), 'label' => 'game.goal_top_half'],
        ];
    }

    public function getTopScorerAwardName(): string
    {
        return 'season.top_scorer';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper';
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
        // Group winner lifts the Nations League trophy (gold); no
        // relegation in v1.
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 1,
                'borderColor' => 'yellow-500',
                'bgColor' => 'bg-yellow-500',
                'label' => 'game.tournament_winner',
            ],
        ];
    }
}
