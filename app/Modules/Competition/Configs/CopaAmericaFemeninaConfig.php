<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

/**
 * Configuration for the Copa América Femenina (WCOPAAM).
 *
 * v1 format: all 10 CONMEBOL nations in a single league phase, following
 * the WorldCupQualifyingConfig pattern. The 1st-placed side is crowned
 * South American champion (gold standings zone).
 *
 * NOTE: in reality the Copa América Femenina also serves as qualifying
 * for the World Cup and the Olympics, but v1 keeps it a standalone
 * continental title — World Cup qualifying stays in WQCONM.
 *
 * Season goals: elite and continental sides are expected to win it
 * (GOAL_TITLE); the rest target a top-half finish.
 *
 * Prize money is mid-tier for a continental title (champion €5M).
 *
 * All money values are well under 2^31 — the Wasmer PHP runtime is 32-bit.
 */
class CopaAmericaFemeninaConfig implements CompetitionConfig, HasSeasonGoals
{
    private const NUM_TEAMS = 10;

    /**
     * TV revenue by position, in cents. Champion earns €5M.
     */
    private const TV_REVENUE = [
        1  => 500_000_000,   // €5M
        2  => 440_000_000,   // €4.4M
        3  => 380_000_000,   // €3.8M
        4  => 340_000_000,   // €3.4M
        5  => 300_000_000,   // €3M
        6  => 270_000_000,   // €2.7M
        7  => 240_000_000,   // €2.4M
        8  => 210_000_000,   // €2.1M
        9  => 190_000_000,   // €1.9M
        10 => 170_000_000,   // €1.7M
    ];

    private const POSITION_FACTORS = [
        'top' => 1.10,        // 1st-2nd (finalists)
        'mid_high' => 1.0,    // 3rd-4th
        'mid_low' => 0.95,    // 5th-6th
        'bottom' => 0.85,     // 7th-10th
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
        return 'Copa América Femenina';
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
        if ($position <= 6) {
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
            Game::GOAL_TOP_HALF => 5, // finish top half
            Game::GOAL_SURVIVAL => 8,
            default => 5,
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
        // 1st place is crowned South American champion (gold).
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
