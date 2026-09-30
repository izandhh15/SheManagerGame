<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

/**
 * Configuration for the UEFA Women's Euro (WEURO).
 *
 * v1 format: 16 teams in a single league phase, following the
 * WorldCupQualifyingConfig pattern. The 1st-placed side is crowned
 * European champion (gold standings zone).
 *
 * Season goals: elite and continental sides are expected to win it
 * (GOAL_TITLE); established sides target the top half, the rest a
 * respectable finish.
 *
 * Prize money is the highest of the new national-team competitions
 * (champion €18M), reflecting the prestige of the continental
 * championship — well above World Cup qualifying (€8M).
 *
 * All money values are well under 2^31 — the Wasmer PHP runtime is 32-bit.
 */
class WomensEuroConfig implements CompetitionConfig, HasSeasonGoals
{
    private const NUM_TEAMS = 16;

    /**
     * TV revenue by position, in cents. Champion earns €18M; even the
     * bottom side takes €3.5M — a major-tournament payout.
     */
    private const TV_REVENUE = [
        1  => 1800_000_000,  // €18M
        2  => 1500_000_000,  // €15M
        3  => 1300_000_000,  // €13M
        4  => 1100_000_000,  // €11M
        5  => 1000_000_000,  // €10M
        6  => 900_000_000,   // €9M
        7  => 800_000_000,   // €8M
        8  => 750_000_000,   // €7.5M
        9  => 700_000_000,   // €7M
        10 => 650_000_000,   // €6.5M
        11 => 600_000_000,   // €6M
        12 => 550_000_000,   // €5.5M
        13 => 500_000_000,   // €5M
        14 => 450_000_000,   // €4.5M
        15 => 400_000_000,   // €4M
        16 => 350_000_000,   // €3.5M
    ];

    private const POSITION_FACTORS = [
        'top' => 1.15,        // 1st-2nd (finalists)
        'mid_high' => 1.05,   // 3rd-4th
        'mid_low' => 0.95,    // 5th-8th
        'bottom' => 0.85,     // 9th-16th
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
        return "UEFA Women's Euro";
    }

    public function getTvRevenue(int $position): int|float
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[self::NUM_TEAMS];
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
        return self::REPUTATION_TO_GOAL[$reputation] ?? Game::GOAL_TOP_HALF;
    }

    public function getGoalTargetPosition(string $goal): int
    {
        return match ($goal) {
            Game::GOAL_TITLE => 1,
            Game::GOAL_TOP_HALF => 8, // finish top half
            Game::GOAL_SURVIVAL => 12,
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
        // 1st place is crowned European champion (gold).
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
