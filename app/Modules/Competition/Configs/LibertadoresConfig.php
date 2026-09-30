<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

/**
 * Copa Libertadores Femenina.
 *
 * Continental club competition for South America. Teams from the
 * Argentine Primera División (ARG1) and Brasileirão Feminino (BRA1)
 * qualify via their domestic leagues; clubs from countries without
 * a modelled league (Chile, Colombia, Paraguay, etc.) come from the
 * LIBERTADORES team pool.
 */
class LibertadoresConfig implements CompetitionConfig
{
    /**
     * Prize money in cents, keyed by rounds remaining after the one won.
     * Based on the real Copa Libertadores Femenina prize pool.
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        0 => 100_000_000,   // €1M   — win Final (champion)
        1 => 50_000_000,    // €500K — win SF = reach Final
        2 => 25_000_000,    // €250K — win QF = reach SF
        3 => 10_000_000,    // €100K — win R16 = reach QF
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
        return 'season.top_scorer_libertadores';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_libertadores';
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
