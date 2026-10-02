<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

/**
 * Configuration for the Olympic women's football tournament 2028 (WOLYMP).
 *
 * Real Olympic format: 12 teams, 3 groups of 4 (single round-robin, 3
 * matchdays). Top 2 per group + the 2 best third-place teams advance to
 * the quarter-finals, then single-leg knockouts: QF → SF → 3rd place →
 * Final. Los Angeles 2028 — the USA are the host nation.
 *
 * In the national-team calendar it runs right after the World Cup 2027:
 * the two World Cup finalists go on to defend/chase Olympic gold
 * (Spain defends the Paris 2024 title).
 *
 * Season goals: elite sides are expected to win it (GOAL_TITLE);
 * continental sides target the knockout stage, the rest a respectable
 * group-stage finish.
 *
 * Prize money sits below the World Cup (champion €10M).
 * All values are in cents and stay under 2^31 — the Wasmer PHP
 * runtime is 32-bit.
 */
class WomensOlympicsConfig implements CompetitionConfig, HasSeasonGoals
{
    private const NUM_TEAMS = 12;

    /**
     * TV revenue by final position, in cents. Champion earns €10M;
     * every participant takes at least €4M.
     */
    private const TV_REVENUE = [
        1 => 1000_000_000,  // €10M
        2 => 850_000_000,   // €8.5M
        3 => 750_000_000,   // €7.5M (SF)
        4 => 750_000_000,
        5 => 600_000_000,   // €6M   (QF)
        6 => 600_000_000,
        7 => 600_000_000,
        8 => 600_000_000,
    ];

    private const GROUP_STAGE_REVENUE = 400_000_000; // €4M (9th-12th)

    private const POSITION_FACTORS = [
        'top' => 1.20,        // 1st-2nd (finalists)
        'mid_high' => 1.10,   // 3rd-4th
        'mid_low' => 1.00,    // 5th-8th (knockout stage)
        'bottom' => 0.90,     // 9th-12th (group stage exit)
    ];

    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_SURVIVAL,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_SURVIVAL,
    ];

    public function getName(): string
    {
        return 'Juegos Olímpicos 2028';
    }

    public function getTvRevenue(int $position): int|float
    {
        return self::TV_REVENUE[$position] ?? self::GROUP_STAGE_REVENUE;
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 2) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 4) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 8) {
            return self::POSITION_FACTORS['mid_low'];
        }
        return self::POSITION_FACTORS['bottom'];
    }

    public function getSeasonGoal(string $reputation): string
    {
        return self::REPUTATION_TO_GOAL[$reputation] ?? Game::GOAL_SURVIVAL;
    }

    public function getGoalTargetPosition(string $goal): int
    {
        return match ($goal) {
            Game::GOAL_TITLE => 1,
            Game::GOAL_TOP_HALF => 8, // reach the quarter-finals
            Game::GOAL_SURVIVAL => 10,
            default => 8,
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
        // Top 2 per group advance to the quarter-finals (green);
        // third-place teams can still go through as best thirds (amber).
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 2,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.knockout_qualified',
            ],
            [
                'minPosition' => 3,
                'maxPosition' => 3,
                'borderColor' => 'amber-500',
                'bgColor' => 'bg-amber-500',
                'label' => 'game.knockout_best_third',
            ],
        ];
    }
}
