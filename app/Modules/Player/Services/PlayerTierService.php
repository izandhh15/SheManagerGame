<?php

namespace App\Modules\Player\Services;

class PlayerTierService
{
    // Market value thresholds in cents (lower bound of each tier).
    // Rescaled to the women's economy (SheManagerGame): max real value €2M,
    // p99 €900K, p90 €200K, median €45K (3,199 players, Soccerdonna).
    public const TIER_5_MIN = 150_000_000;  // €1.5M+  World Class
    public const TIER_4_MIN =  80_000_000;  // €800K+  Excellent
    public const TIER_3_MIN =  30_000_000;  // €300K+  Good
    public const TIER_2_MIN =  10_000_000;  // €100K+  Average
    // Tier 1: below €100K                   Developing

    /**
     * Compute tier from market value in cents (pure function, no DB).
     */
    public static function tierFromMarketValue(int $marketValueCents): int
    {
        return match (true) {
            $marketValueCents >= self::TIER_5_MIN => 5,
            $marketValueCents >= self::TIER_4_MIN => 4,
            $marketValueCents >= self::TIER_3_MIN => 3,
            $marketValueCents >= self::TIER_2_MIN => 2,
            default => 1,
        };
    }

}
