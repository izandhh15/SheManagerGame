<?php

namespace App\Modules\Stadium\Services;

use App\Models\Game;

/**
 * Matchday pricing set by the manager (club mode): single ticket, official
 * shirt, merchandising and stadium bar drink prices, in euros.
 *
 * DB columns (games.ticket_price, shirt_price, merch_price, bar_price) are
 * nullable; null means the game default.
 */
class MatchdayPricingService
{
    public const DEFAULT_TICKET = 15;
    public const DEFAULT_SHIRT = 70;
    public const DEFAULT_MERCH = 20;
    public const DEFAULT_BAR = 4;

    // Price bands offered on the Matchday page (euros).
    public const MIN_TICKET = 5;
    public const MAX_TICKET = 60;
    public const MIN_SHIRT = 20;
    public const MAX_SHIRT = 150;
    public const MIN_MERCH = 5;
    public const MAX_MERCH = 60;
    public const MIN_BAR = 1;
    public const MAX_BAR = 12;

    /**
     * Resolved prices for the game, falling back to the defaults.
     *
     * @return array{ticket: int, shirt: int, merch: int, bar: int}
     */
    public function prices(Game $game): array
    {
        return [
            'ticket' => (int) ($game->ticket_price ?? self::DEFAULT_TICKET),
            'shirt' => (int) ($game->shirt_price ?? self::DEFAULT_SHIRT),
            'merch' => (int) ($game->merch_price ?? self::DEFAULT_MERCH),
            'bar' => (int) ($game->bar_price ?? self::DEFAULT_BAR),
        ];
    }

    /**
     * Revenue breakdown for a home fixture given its attendance (euros).
     *
     * The manager faces a real trade-off: higher prices earn more per fan
     * but pull demand down — cheaper entry sells more shirts, scarves and
     * drinks; raising the ticket price inflates the gate but empties the
     * shop and the bar.
     *
     * @return array{tickets: int, shirts: int, merch: int, bars: int, total: int}
     */
    public function revenueForAttendance(int $attendance, array $prices): array
    {
        $ticket = max(1, (int) ($prices['ticket'] ?? self::DEFAULT_TICKET));
        $shirt = max(1, (int) ($prices['shirt'] ?? self::DEFAULT_SHIRT));
        $merch = max(1, (int) ($prices['merch'] ?? self::DEFAULT_MERCH));
        $bar = max(1, (int) ($prices['bar'] ?? self::DEFAULT_BAR));

        $tickets = $attendance * $ticket;

        // Buyers as a share of the crowd, price-elastic around the default:
        // halving the price more than doubles buyers; doubling it halves them.
        $shirtBuyers = $attendance * 0.02 * $this->elasticity($shirt, self::DEFAULT_SHIRT);
        $merchBuyers = $attendance * 0.08 * $this->elasticity($merch, self::DEFAULT_MERCH);
        $barBuyers = $attendance * 0.45 * $this->elasticity($bar, self::DEFAULT_BAR, 0.4, 2.0);

        $shirts = (int) round($shirtBuyers * $shirt);
        $merchRev = (int) round($merchBuyers * $merch);
        $bars = (int) round($barBuyers * $bar);

        return [
            'tickets' => $tickets,
            'shirts' => $shirts,
            'merch' => $merchRev,
            'bars' => $bars,
            'total' => $tickets + $shirts + $merchRev + $bars,
        ];
    }

    /**
     * Demand multiplier relative to the default price. Clamped so extreme
     * prices stay playable instead of collapsing demand to zero.
     */
    private function elasticity(int $price, int $default, float $min = 0.3, float $max = 2.5): float
    {
        return min($max, max($min, $default / $price));
    }

    /**
     * How the ticket price scales matchday attendance: pricey entry cools
     * the crowd down, cheap entry warms it up. Defaults are the reference.
     */
    public function attendanceFactor(int $ticketPrice): float
    {
        return min(1.1, max(0.75, self::DEFAULT_TICKET / max(1, $ticketPrice)));
    }
}
