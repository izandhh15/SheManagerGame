<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

class LaLigaConfig implements CompetitionConfig, HasSeasonGoals
{
    /**
     * Liga F TV revenue by position (in cents). 16 teams, ~€7.4M total pool
     * (SheManagerGame rescale of the ~€700M La Liga pool ÷100).
     */
    private const TV_REVENUE = [
        1 => 75_000_000,   // €750K
        2 => 70_000_000,   // €700K
        3 => 62_000_000,   // €620K
        4 => 58_000_000,   // €580K
        5 => 55_000_000,   // €550K
        6 => 52_000_000,   // €520K
        7 => 49_000_000,   // €490K
        8 => 46_000_000,   // €460K
        9 => 43_000_000,   // €430K
        10 => 40_000_000,  // €400K
        11 => 37_000_000,  // €370K
        12 => 35_000_000,  // €350K
        13 => 33_000_000,  // €330K
        14 => 31_000_000,  // €310K
        15 => 29_500_000,  // €295K
        16 => 28_000_000,  // €280K
    ];

    private const POSITION_FACTORS = [
        'top' => 1.10,        // 1st-4th
        'mid_high' => 1.0,    // 5th-8th
        'mid_low' => 0.95,    // 9th-14th
        'relegation' => 0.85, // 15th-16th
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_TITLE => ['targetPosition' => 1, 'label' => 'game.goal_title'],
        Game::GOAL_EUROPA_LEAGUE => ['targetPosition' => 6, 'label' => 'game.goal_europa_league'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 10, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 15, 'label' => 'game.goal_survival'],
    ];

    /**
     * Map reputation to season goal.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_EUROPA_LEAGUE,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_SURVIVAL,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_SURVIVAL,
    ];

    public function getTvRevenue(int $position): int
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[16];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 4) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 8) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 14) {
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
        return self::SEASON_GOALS[$goal]['targetPosition'] ?? 10;
    }

    public function getAvailableGoals(): array
    {
        return self::SEASON_GOALS;
    }

    public function getTopScorerAwardName(): string
    {
        return 'season.pichichi';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.zamora';
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
        $slots = config('countries.ES.continental_slots.ESP1', []);
        $promotions = config('countries.ES.promotions', []);

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

        if (!empty($slots['UECL'])) {
            $zones[] = [
                'minPosition' => min($slots['UECL']),
                'maxPosition' => max($slots['UECL']),
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.conference_league',
            ];
        }

        $relegation = collect($promotions)->first(fn ($r) => $r['top_division'] === 'ESP1');
        if ($relegation && !empty($relegation['relegated_positions'])) {
            $zones[] = [
                'minPosition' => min($relegation['relegated_positions']),
                'maxPosition' => max($relegation['relegated_positions']),
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.relegation',
            ];
        }

        return $zones;
    }

}
