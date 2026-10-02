<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

/**
 * Configuration for the women's Club World Cup (CWC).
 *
 * 32 teams (16 UEFA, 8 CONMEBOL, 8 CONCACAF — the confederations with
 * real club data in the game), 8 groups of 4, top 2 per group to the
 * round of 16, then single-leg knockouts: R16 → QF → SF → Final.
 *
 * The prize money is the richest in the game: lifting the trophy pays
 * €2M on top of the per-round bonuses, because the whole point of the
 * tournament is that it's the biggest club prize in world football.
 * All values are in cents and stay under 2^31 — the Wasmer PHP
 * runtime is 32-bit.
 */
class ClubWorldCupConfig implements CompetitionConfig
{
    /**
     * Knockout prize money (in cents), paid per round WON (accumulative,
     * see AwardCupPrizeMoney). Keyed by rounds remaining after the one
     * won: 0 is the final.
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        0 => 200_000_000,  // €2M   — win the Final (champions)
        1 => 120_000_000,  // €1.2M — win the SF (finalists)
        2 => 70_000_000,   // €700K — win the QF (semi-finalists)
        3 => 40_000_000,   // €400K — win the R16 (quarter-finalists)
    ];

    /**
     * Participation + final-position revenue (in cents). Every club that
     * reaches the tournament banks at least €500K; the champions take €2M.
     */
    private const TV_REVENUE = [
        1 => 200_000_000,  // €2M   — champions
        2 => 150_000_000,  // €1.5M — runners-up
        3 => 120_000_000,  // €1.2M — semi-finalists
        4 => 120_000_000,
    ];

    private const KNOCKOUT_STAGE_REVENUE = 100_000_000;  // €1M   — QF (5th-8th)
    private const ROUND_OF_16_REVENUE = 80_000_000;      // €800K — R16 (9th-16th)
    private const GROUP_STAGE_REVENUE = 50_000_000;      // €500K — group exit (17th-32nd)

    public function getTvRevenue(int $position): int|float
    {
        if (isset(self::TV_REVENUE[$position])) {
            return self::TV_REVENUE[$position];
        }
        if ($position <= 8) {
            return self::KNOCKOUT_STAGE_REVENUE;
        }
        if ($position <= 16) {
            return self::ROUND_OF_16_REVENUE;
        }

        return self::GROUP_STAGE_REVENUE;
    }

    public function getPositionFactor(int $position): float
    {
        return match (true) {
            $position <= 2 => 1.30,
            $position <= 4 => 1.20,
            $position <= 8 => 1.10,
            $position <= 16 => 1.00,
            default => 0.90,
        };
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
        // Top 2 per group reach the round of 16 (green).
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 2,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.knockout_qualified',
            ],
        ];
    }
}
