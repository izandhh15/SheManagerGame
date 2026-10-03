<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

/**
 * Config for the UEFA Women's Europa Cup (UWEC), played since 2026-27 as a
 * pure two-legged knockout from the round of 16 — no league phase:
 * R16 → QF → SF → F. The 16 teams are the winners of the two UELQ
 * qualifying rounds (12 round-1 winners + 12 direct round-2 entrants +
 * 8 UWCL qualifying losers, halved twice); nobody enters the Europa Cup
 * directly. The winner takes a UWCL place for the following season.
 */
class EuropaLeagueConfig implements CompetitionConfig
{
    /**
     * UWEC knockout prize money (in cents), real figures from UEFA circular
     * 50/2026 adapted to the four knockout rounds (R16 €70K, QF €70K,
     * SF €75K, runner-up €75K, champion €80K). Paid per round WON
     * (accumulative).
     * Keyed by rounds remaining after the one won: 0 is the final.
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        0 => 8_000_000,    // €80K — win Final (champion)
        1 => 7_500_000,    // €75K — win SF = reach Final (runner-up)
        2 => 7_500_000,    // €75K — win QF = reach SF
        3 => 7_000_000,    // €70K — win R16 = reach QF
    ];

    public function getTvRevenue(int $position): int|float
    {
        // No league phase, no TV ranking money: the knockout prizes and
        // the UWCL place for the winner are the reward.
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
        return self::KNOCKOUT_PRIZE_MONEY[$roundsFromFinal] ?? 0;
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
