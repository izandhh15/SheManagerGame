<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

/**
 * Configuration for the FIFA Women's World Cup 2027 (WWCU27).
 *
 * Real FIFA format: 32 teams, 8 groups of 4 (single round-robin, 3
 * matchdays). Top 2 per group advance to the Round of 16, then
 * single-leg knockouts: R16 → QF → SF → 3rd place → Final.
 *
 * Season goals: elite sides are expected to win it (GOAL_TITLE);
 * continental sides target the knockout stage, the rest a respectable
 * group-stage finish.
 *
 * Prize money is the highest of any national-team competition
 * (champion €20M). All values are in cents and stay under 2^31 —
 * the Wasmer PHP runtime is 32-bit.
 */
class WomensWorldCupConfig implements CompetitionConfig, HasSeasonGoals
{
    private const NUM_TEAMS = 32;

    /**
     * TV revenue by final position, in cents. Champion earns €20M;
     * every participant takes at least €7M — a World Cup payout.
     */
    private const TV_REVENUE = [
        1  => 2000_000_000,  // €20M
        2  => 1700_000_000,  // €17M
        3  => 1500_000_000,  // €15M
        4  => 1300_000_000,  // €13M
        5  => 1100_000_000,  // €11M  (QF)
        6  => 1100_000_000,
        7  => 1100_000_000,
        8  => 1100_000_000,
        9  => 900_000_000,   // €9M   (R16)
        10 => 900_000_000,
        11 => 900_000_000,
        12 => 900_000_000,
        13 => 900_000_000,
        14 => 900_000_000,
        15 => 900_000_000,
        16 => 900_000_000,
    ];

    private const GROUP_STAGE_REVENUE = 700_000_000; // €7M (17th-32nd)

    private const POSITION_FACTORS = [
        'top' => 1.20,        // 1st-2nd (finalists)
        'mid_high' => 1.10,   // 3rd-4th
        'mid_low' => 1.00,    // 5th-16th (knockout stage)
        'bottom' => 0.90,     // 17th-32nd (group stage exit)
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
        return "FIFA Women's World Cup 2027";
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
        if ($position <= 16) {
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
            Game::GOAL_TOP_HALF => 16, // reach the knockout stage
            Game::GOAL_SURVIVAL => 24,
            default => 16,
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
        // Top 2 per group advance to the Round of 16 (green).
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 2,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.knockout_qualified',
            ],
        ];
    }
}
