<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

/**
 * EFL Cup prize money (in cents), keyed by rounds remaining after the tie
 * just won: 0 is the final.
 *
 * The EFL Cup is the lesser of England's two cups — it is played midweek,
 * routinely met with a rotated side, and pays a fraction of what the FA Cup
 * does. Half the KnockoutCupConfig figure at every stage keeps that gap
 * visible in the accounts.
 *
 * It needs its own table rather than sharing the generic one because its
 * bracket is shorter: a four-round cup reusing KnockoutCupConfig would open
 * on the round-of-16 rate, paying more for its early rounds than the FA Cup
 * pays for its own.
 */
class EflCupConfig implements CompetitionConfig
{
    private const KNOCKOUT_PRIZE_MONEY = [
        0 => 1_000_000,  // €10K  - Final
        1 => 500_000,    // €5K   - Semi-finals
        2 => 250_000,    // €2.5K - Quarter-finals
        3 => 150_000,    // €1.5K - Fourth round
        4 => 100_000,    // €1K   - Third round
    ];

    public function getTvRevenue(int $position): int
    {
        return 0;
    }

    public function getPositionFactor(int $position): float
    {
        return 1.0;
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
}
