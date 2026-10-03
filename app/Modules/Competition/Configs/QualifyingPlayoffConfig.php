<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

/**
 * Config for the UEFA qualifying playoffs (UCLQ / UELQ).
 *
 * Two-legged knockout ties played in August, before the league phases.
 * UCLQ winners reach the UWCL league phase, losers drop into UELQ round 2;
 * UELQ round-2 winners reach the Europa Cup knockout phase (round of 16),
 * losers of any UELQ round are out.
 */
class QualifyingPlayoffConfig implements CompetitionConfig
{
    /**
     * Qualifying playoff prize money (in cents). Modest — the real reward
     * is the league-phase place (and its TV money).
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        0 => 5_000_000,   // €50K — win the playoff tie
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
