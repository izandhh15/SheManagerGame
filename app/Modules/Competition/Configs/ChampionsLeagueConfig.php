<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

class ChampionsLeagueConfig implements CompetitionConfig
{
    /**
     * UWCL knockout prize money (in cents), real figures from UEFA circular
     * 50/2026. Paid per round WON (accumulative, see AwardCupPrizeMoney).
     * Keyed by rounds remaining after the one won: 0 is the final.
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        0 => 50_000_000,    // €500K — win Final (champion)
        1 => 30_000_000,    // €300K — win SF = reach Final (runner-up)
        2 => 25_000_000,    // €250K — win QF = reach SF
        3 => 20_000_000,    // €200K — win R16 = reach QF
        4 => 10_000_000,    // €100K — win Knockout Playoff = reach R16
    ];

    /**
     * UWCL prize money by league phase position (in cents). Real figures from
     * UEFA circular 50/2026: €505K flat participation + €10K/position ranking
     * bonus (€180K for 1st … €10K for last), extended here across the 36
     * in-game league-phase slots. NOTE: the real €60K/win + €20K/draw
     * performance bonuses are not modeled — this engine settles TV once per
     * season by final position (see SeasonSettlementProcessor).
     */
    private const TV_REVENUE = [
        1 => 86_500_000,    // €865K
        2 => 85_500_000,    // €855K
        3 => 84_500_000,    // €845K
        4 => 83_500_000,    // €835K
        5 => 82_500_000,    // €825K
        6 => 81_500_000,    // €815K
        7 => 80_500_000,    // €805K
        8 => 79_500_000,    // €795K (direct R16)
        9 => 78_500_000,    // €785K
        10 => 77_500_000,    // €775K
        11 => 76_500_000,    // €765K
        12 => 75_500_000,    // €755K
        13 => 74_500_000,    // €745K
        14 => 73_500_000,    // €735K
        15 => 72_500_000,    // €725K
        16 => 71_500_000,    // €715K
        17 => 70_500_000,    // €705K
        18 => 69_500_000,    // €695K
        19 => 68_500_000,    // €685K
        20 => 67_500_000,    // €675K
        21 => 66_500_000,    // €665K
        22 => 65_500_000,    // €655K
        23 => 64_500_000,    // €645K
        24 => 63_500_000,    // €635K (last playoff spot)
        25 => 62_500_000,    // €625K (eliminated)
        26 => 61_500_000,    // €615K
        27 => 60_500_000,    // €605K
        28 => 59_500_000,    // €595K
        29 => 58_500_000,    // €585K
        30 => 57_500_000,    // €575K
        31 => 56_500_000,    // €565K
        32 => 55_500_000,    // €555K
        33 => 54_500_000,    // €545K
        34 => 53_500_000,    // €535K
        35 => 52_500_000,    // €525K
        36 => 51_500_000,    // €515K
    ];

    public function getTvRevenue(int $position): int|float
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[36];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 8) {
            return 1.15;
        }
        if ($position <= 24) {
            return 1.05;
        }

        return 0.95;
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
            return 10_000_000; // €100K — direct R16, skips the €100K playoff prize
        }
        if ($position <= 24) {
            return 0; // these teams earn the €100K reach-R16 prize by winning the playoff
        }

        return 0; // Eliminated
    }

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 8,
                'borderColor' => 'blue-500',
                'bgColor' => 'bg-blue-500',
                'label' => 'game.ucl_direct_knockout',
            ],
            [
                'minPosition' => 9,
                'maxPosition' => 24,
                'borderColor' => 'yellow-500',
                'bgColor' => 'bg-yellow-500',
                'label' => 'game.ucl_knockout_playoff',
            ],
            [
                'minPosition' => 25,
                'maxPosition' => 36,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.ucl_eliminated',
            ],
        ];
    }

}
