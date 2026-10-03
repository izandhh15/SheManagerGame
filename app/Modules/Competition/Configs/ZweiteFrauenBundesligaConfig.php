<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

class ZweiteFrauenBundesligaConfig implements CompetitionConfig, HasSeasonGoals
{
    /**
     * 2. Frauen-Bundesliga TV revenue by position (in cents). 14 teams.
     */
    private const TV_REVENUE = [
        1 => 60_000_000,      // €600K
        2 => 55_000_000,      // €550K
        3 => 50_000_000,      // €500K
        4 => 46_000_000,      // €460K
        5 => 42_000_000,      // €420K
        6 => 39_000_000,      // €390K
        7 => 36_000_000,      // €360K
        8 => 33_000_000,      // €330K
        9 => 30_000_000,      // €300K
        10 => 28_000_000,     // €280K
        11 => 26_000_000,     // €260K
        12 => 24_000_000,     // €240K
        13 => 22_000_000,     // €220K
        14 => 20_000_000,     // €200K
    ];

    private const POSITION_FACTORS = [
        'top' => 1.05,        // 1st-4th (promotion zone)
        'mid_high' => 1.0,    // 5th-10th
        'mid_low' => 0.95,    // 11th
        'relegation' => 0.85, // 12th-14th
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_PROMOTION => ['targetPosition' => 2, 'label' => 'game.goal_promotion'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 7, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 11, 'label' => 'game.goal_survival'],
    ];

    /**
     * Map reputation to season goal.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_PROMOTION,
        // No promotion playoff in the 2. Frauen-Bundesliga (playoff_count=0):
        // established clubs aim for direct promotion instead.
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_SURVIVAL,
    ];

    public function getTvRevenue(int $position): int|float
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[14];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 4) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 10) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 11) {
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
        return 'season.top_scorer_deu2';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_deu2';
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
        $promotions = config('countries.DE.promotions', []);
        $rule = collect($promotions)->first(fn ($r) => $r['bottom_division'] === 'DEU2');

        $zones = [];

        $directCount = (int) ($rule['direct_count'] ?? 0);
        $playoffCount = (int) ($rule['playoff_count'] ?? 0);

        if ($rule && $directCount > 0) {
            $zones[] = [
                'minPosition' => 1,
                'maxPosition' => $directCount,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.direct_promotion',
            ];
        }

        if ($rule && $playoffCount > 0) {
            $zones[] = [
                'minPosition' => $directCount + 1,
                'maxPosition' => $directCount + $playoffCount,
                'borderColor' => 'green-300',
                'bgColor' => 'bg-green-300',
                'label' => 'game.promotion_playoff',
            ];
        }

        // No relegation zone: the 2. Frauen-Bundesliga has no lower division in the game,
        // so no relegation is modeled (see config/countries.php promotions).
        return $zones;
    }
}
