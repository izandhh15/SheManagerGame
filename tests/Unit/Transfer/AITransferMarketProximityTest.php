<?php

namespace Tests\Unit\Transfer;

use App\Modules\Transfer\Services\AITransferMarketService;
use ReflectionClass;
use Tests\TestCase;

/**
 * Market proximity scoring: AI clubs should sign the way real clubs do —
 * mostly from their own league, then from neighbouring leagues and adjacent
 * tiers (a Liga F club shops in Liga F or at the top of Primera RFEF).
 *
 * marketProximityScore is pure logic, tested here via reflection without a DB.
 */
class AITransferMarketProximityTest extends TestCase
{
    private function score(?array $buyer, ?array $seller): int
    {
        $service = (new ReflectionClass(AITransferMarketService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($service, 'marketProximityScore');
        $method->setAccessible(true);

        return $method->invoke($service, $buyer, $seller);
    }

    private function league(string $competition, ?int $tier, ?int $position, int $size, ?string $country = 'ES'): array
    {
        return [
            'competition_id' => $competition,
            'tier' => $tier,
            'country' => $country,
            'position' => $position,
            'top_half' => $position !== null && $position <= (int) ceil($size / 2),
        ];
    }

    public function test_same_league_scores_highest(): void
    {
        $this->assertSame(14, $this->score(
            $this->league('ESP1', 1, 5, 16),
            $this->league('ESP1', 1, 10, 16),
        ));
    }

    public function test_adjacent_tier_top_half_gets_parte_alta_bonus(): void
    {
        // Liga F club signing from a top-half Primera RFEF club: 10 + 6.
        $this->assertSame(16, $this->score(
            $this->league('ESP1', 1, 5, 16),
            $this->league('ESP2', 2, 2, 14),
        ));
    }

    public function test_adjacent_tier_bottom_half_gets_no_bonus(): void
    {
        $this->assertSame(10, $this->score(
            $this->league('ESP1', 1, 5, 16),
            $this->league('ESP2', 2, 12, 14),
        ));
    }

    public function test_same_tier_neighbouring_league(): void
    {
        // ESP3A ↔ ESP3B groups.
        $this->assertSame(8, $this->score(
            $this->league('ESP3A', 3, 4, 14),
            $this->league('ESP3B', 3, 9, 14),
        ));
    }

    public function test_two_tier_gap_scores_low(): void
    {
        $this->assertSame(4, $this->score(
            $this->league('ESP1', 1, 5, 16),
            $this->league('ESP3A', 3, 1, 14),
        ));
    }

    public function test_foreign_seller_scores_zero(): void
    {
        $this->assertSame(0, $this->score(
            $this->league('ESP1', 1, 5, 16),
            $this->league('ENG1', 1, 2, 12, 'EN'),
        ));
    }

    public function test_missing_league_data_scores_zero(): void
    {
        $this->assertSame(0, $this->score(null, $this->league('ESP1', 1, 5, 16)));
        $this->assertSame(0, $this->score($this->league('ESP1', 1, 5, 16), null));
    }

    public function test_unknown_tier_falls_back_to_country_bonus(): void
    {
        $this->assertSame(4, $this->score(
            $this->league('ESP1', null, 5, 16),
            $this->league('ESP2', 2, 2, 14),
        ));
    }
}
