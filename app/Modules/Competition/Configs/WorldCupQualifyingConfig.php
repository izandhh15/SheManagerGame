<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

/**
 * Configuration for the Women's World Cup qualifying competitions, one per
 * FIFA confederation (WQUEFA, WQAFC, WQCAF, WQCONC, WQCONM, WQOFC), plus the
 * legacy WWCQ alias.
 *
 * v1 format: qualifying runs as a drawn group of 6 with a 10-matchday
 * double round-robin. The top 2 qualify for the 2027 World Cup (green
 * standings zone); everyone else is out — there is no relegation and no
 * prize ladder beyond a modest participation payout.
 *
 * Season goals: the only meaningful goal in a qualifying group is finishing
 * top 2 (mapped to GOAL_TOP_HALF, "qualify"); elite sides are expected to
 * win the group (GOAL_TITLE).
 *
 * All money values are well under 2^31 — the Wasmer PHP runtime is 32-bit.
 */
class WorldCupQualifyingConfig implements CompetitionConfig, HasSeasonGoals
{
    private const NUM_TEAMS = 6;

    /**
     * TV revenue by position, in cents. A flat solidarity-style payout for a
     * short qualifying campaign: champions earn barely twice the bottom side.
     */
    private const TV_REVENUE = [
        1 => 800_000_000,   // €8M
        2 => 700_000_000,   // €7M
        3 => 560_000_000,   // €5.6M
        4 => 460_000_000,   // €4.6M
        5 => 380_000_000,   // €3.8M
        6 => 320_000_000,   // €3.2M
    ];

    private const POSITION_FACTORS = [
        'top' => 1.10,        // 1st-2nd (qualified)
        'mid_high' => 1.0,    // 3rd
        'mid_low' => 0.95,    // 4th
        'bottom' => 0.85,     // 5th-6th
    ];

    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_TOP_HALF,
    ];

    public function getTvRevenue(int $position): int|float
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[self::NUM_TEAMS];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 2) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position === 3) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position === 4) {
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
            Game::GOAL_TOP_HALF => 2, // finish top 2 = qualify
            Game::GOAL_SURVIVAL => 4,
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
        // Top 2 qualify for the 2027 World Cup (green); no relegation in a
        // qualifier, so this is the only zone.
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 2,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.world_cup_qualified',
            ],
        ];
    }
}
