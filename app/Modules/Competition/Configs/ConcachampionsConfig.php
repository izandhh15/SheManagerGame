<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

/**
 * CONCACAF W Champions Cup (Concachampions Femenina).
 *
 * Continental club competition for North/Central America and the Caribbean.
 * Teams from Liga MX Femenil (MEX1) and NWSL (USA1) qualify via their
 * domestic leagues; clubs from countries without a modelled league
 * (Costa Rica, Panama, etc.) come from the CONCACHAMPIONS team pool.
 */
class ConcachampionsConfig implements CompetitionConfig
{
    /**
     * Prize money in cents, keyed by rounds remaining after the one won.
     * Based on the real CONCACAF W Champions Cup prize pool.
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        0 => 50_000_000,    // €500K — win Final (champion)
        1 => 25_000_000,    // €250K — win SF = reach Final
        2 => 12_500_000,    // €125K — win QF = reach SF
        3 => 5_000_000,     // €50K  — win R16 = reach QF
    ];

    public function getTvRevenue(int $position): int|float
    {
        return 0;
    }

    public function getPositionFactor(int $position): float
    {
        return 1.0;
    }

    public function getTopScorerAwardName(): string
    {
        return 'season.top_scorer_concachampions';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_concachampions';
    }

    public function getKnockoutPrizeMoney(int $roundsFromFinal): int
    {
        return self::KNOCKOUT_PRIZE_MONEY[$roundsFromFinal]
            ?? self::KNOCKOUT_PRIZE_MONEY[array_key_last(self::KNOCKOUT_PRIZE_MONEY)];
    }

    public function getLeaguePhaseQualificationBonus(int $position): int
    {
        return 0;
    }

    public function getStandingsZones(): array
    {
        return [];
    }

    public function getSeasonGoals(): array
    {
        return [];
    }
}
