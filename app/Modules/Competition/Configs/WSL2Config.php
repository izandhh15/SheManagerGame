<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

class WSL2Config implements CompetitionConfig, HasSeasonGoals
{
    /**
     * WSL2 TV revenue by position (in cents). 12 teams.
     *
     * The champions go straight up to the WSL; the runners-up enter the
     * WSL relegation playoff against the 13th-placed WSL side. This league
     * uses the 'league_with_playoff' handler so the bracket fires when the
     * regular season ends (generator + promotions rule wired separately).
     */
    private const TV_REVENUE = [
        1 => 80_000_000,      // €800K
        2 => 72_000_000,      // €720K
        3 => 65_000_000,      // €650K
        4 => 59_000_000,      // €590K
        5 => 54_000_000,      // €540K
        6 => 50_000_000,      // €500K
        7 => 46_000_000,      // €460K
        8 => 42_000_000,      // €420K
        9 => 38_000_000,      // €380K
        10 => 35_000_000,     // €350K
        11 => 32_000_000,     // €320K
        12 => 30_000_000,     // €300K
    ];

    private const POSITION_FACTORS = [
        'top' => 1.05,        // 1st-2nd (promotion / playoff zone)
        'mid_high' => 1.0,    // 3rd-8th
        'mid_low' => 0.95,    // 9th-11th
        'relegation' => 0.85, // 12th
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_PROMOTION => ['targetPosition' => 1, 'label' => 'game.goal_promotion'],
        Game::GOAL_PLAYOFF => ['targetPosition' => 2, 'label' => 'game.goal_playoff'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 6, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 11, 'label' => 'game.goal_survival'],
    ];

    /**
     * Map reputation to season goal.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_PLAYOFF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_SURVIVAL,
    ];

    public function getTvRevenue(int $position): int|float
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[12];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 2) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 8) {
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
        return self::SEASON_GOALS[$goal]['targetPosition'] ?? 9;
    }

    public function getAvailableGoals(): array
    {
        return self::SEASON_GOALS;
    }

    public function getTopScorerAwardName(): string
    {
        return 'season.top_scorer_eng2';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_eng2';
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
        $promotions = config('countries.EN.promotions', []);
        $rule = collect($promotions)->first(fn ($r) => $r['bottom_division'] === 'ENG2');

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

        // WSL2 is the bottom English tier (12 teams): nobody is relegated
        // from it, so there is no relegation zone to paint (the old 12-12
        // range was impossible). The runners-up enter the ENGPO relegation
        // playoff against the WSL 13th (countries.EN promotions), so the
        // 2nd place gets a promotion-playoff zone.
        $zones[] = [
            'minPosition' => 2,
            'maxPosition' => 2,
            'borderColor' => 'green-300',
            'bgColor' => 'bg-green-300',
            'label' => 'game.promotion_playoff',
        ];

        return $zones;
    }
}
