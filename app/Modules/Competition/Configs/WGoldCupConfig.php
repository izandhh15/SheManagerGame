<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

/**
 * Configuration for the CONCACAF W Gold Cup (WGOLD).
 *
 * v1 format: 12 teams in a single league phase, following the
 * WorldCupQualifyingConfig pattern. The 1st-placed side lifts the
 * Gold Cup (gold standings zone).
 *
 * Season goals: elite and continental sides are expected to win it
 * (GOAL_TITLE); the rest target a top-half finish.
 *
 * Prize money is mid-tier for a continental title (champion €6M) —
 * below the UEFA competitions but a meaningful purse for the region.
 *
 * All money values are well under 2^31 — the Wasmer PHP runtime is 32-bit.
 */
class WGoldCupConfig implements CompetitionConfig, HasSeasonGoals
{
    private const NUM_TEAMS = 12;

    /**
     * TV revenue by position, in cents. Champion earns €6M; the
     * shortest payout of the new continental tournaments.
     */
    private const TV_REVENUE = [
        1  => 600_000_000,   // €6M
        2  => 520_000_000,   // €5.2M
        3  => 460_000_000,   // €4.6M
        4  => 400_000_000,   // €4M
        5  => 360_000_000,   // €3.6M
        6  => 320_000_000,   // €3.2M
        7  => 280_000_000,   // €2.8M
        8  => 250_000_000,   // €2.5M
        9  => 220_000_000,   // €2.2M
        10 => 200_000_000,   // €2M
        11 => 180_000_000,   // €1.8M
        12 => 160_000_000,   // €1.6M
    ];

    private const POSITION_FACTORS = [
        'top' => 1.10,        // 1st-2nd (finalists)
        'mid_high' => 1.0,    // 3rd-4th
        'mid_low' => 0.95,    // 5th-8th
        'bottom' => 0.85,     // 9th-12th
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
        return 'CONCACAF W Gold Cup';
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
            Game::GOAL_TOP_HALF => 6, // finish top half
            Game::GOAL_SURVIVAL => 9,
            default => 6,
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
        // 1st place lifts the Gold Cup (gold).
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
