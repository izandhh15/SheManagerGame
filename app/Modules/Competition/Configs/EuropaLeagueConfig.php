<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

class EuropaLeagueConfig implements CompetitionConfig
{
    /**
     * UEL knockout round prize money (in cents).
     */
    /** Keyed by rounds remaining after the one won: 0 is the final. */
    /**
     * UWEC knockout prize money (in cents), real figures from UEFA circular
     * 50/2026 (Q1 €60K, Q2 €65K, R16 €70K, QF €70K, SF €75K, runner-up €75K,
     * champion €80K — €5.6M pool). Paid per round WON (accumulative).
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        0 => 8_000_000,    // €80K — win Final (champion)
        1 => 7_500_000,    // €75K — win SF = reach Final (runner-up)
        2 => 7_500_000,    // €75K — win QF = reach SF
        3 => 7_000_000,    // €70K — win R16 = reach QF
        4 => 7_000_000,    // €70K — win Knockout Playoff = reach R16
    ];

    public function getTvRevenue(int $position): int|float
    {
        // UEFA Women's Europa Cup: €65K base per club + €1K/position ranking
        // (circular 50/2026), extended across the 36 in-game league-phase slots.
        $base = 6_500_000; // €65K floor
        $positionBonus = max(0, 37 - $position) * 100_000; // €1K per position

        return $base + $positionBonus;
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 8) {
            return 1.10;
        }
        if ($position <= 24) {
            return 1.0;
        }

        return 0.90;
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
        if ($position <= 8) {
            return 7_000_000; // €70K — direct R16, skips the €70K playoff prize
        }
        if ($position <= 24) {
            return 0; // these teams earn the €70K reach-R16 prize by winning the playoff
        }

        return 0; // Eliminated
    }

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 8,
                'borderColor' => 'orange-500',
                'bgColor' => 'bg-orange-500',
                'label' => 'game.uel_direct_knockout',
            ],
            [
                'minPosition' => 9,
                'maxPosition' => 24,
                'borderColor' => 'yellow-500',
                'bgColor' => 'bg-yellow-500',
                'label' => 'game.uel_knockout_playoff',
            ],
            [
                'minPosition' => 25,
                'maxPosition' => 36,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.uel_eliminated',
            ],
        ];
    }

}
