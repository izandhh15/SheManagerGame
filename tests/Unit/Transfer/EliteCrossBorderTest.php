<?php

namespace Tests\Unit\Transfer;

use App\Modules\Transfer\Services\AITransferMarketService;
use Tests\TestCase;

/**
 * Elite cross-border transfers: super-clubs shop across borders
 * (Barça → Man City, Lyon → Chelsea). When buyer and seller are both
 * elite/continental but in different countries, the AI gets a bonus so
 * the European elite market actually happens.
 */
class EliteCrossBorderTest extends TestCase
{
    private function bonus(?array $buyer, ?array $seller, int $buyerRep, int $sellerRep): int
    {
        $service = (new \ReflectionClass(AITransferMarketService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($service, 'eliteCrossBorderBonus');
        $method->setAccessible(true);

        return $method->invoke($service, $buyer, $seller, $buyerRep, $sellerRep);
    }

    private function league(string $country): array
    {
        return ['competition_id' => 'X1', 'tier' => 1, 'country' => $country];
    }

    public function test_elite_to_elite_across_borders_gets_bonus(): void
    {
        // Man City (EN, elite) buying from Barça (ES, elite)
        $this->assertSame(8, $this->bonus($this->league('EN'), $this->league('ES'), 0, 0));
    }

    public function test_continental_to_elite_across_borders_gets_bonus(): void
    {
        $this->assertSame(8, $this->bonus($this->league('EN'), $this->league('FR'), 0, 1));
    }

    public function test_same_country_gets_nothing(): void
    {
        $this->assertSame(0, $this->bonus($this->league('ES'), $this->league('ES'), 0, 0));
    }

    public function test_non_elite_gets_nothing(): void
    {
        // Mid-table cross-border moves stay rare — no bonus
        $this->assertSame(0, $this->bonus($this->league('EN'), $this->league('ES'), 2, 2));
        $this->assertSame(0, $this->bonus($this->league('EN'), $this->league('ES'), 0, 3));
    }

    public function test_null_league_gets_nothing(): void
    {
        $this->assertSame(0, $this->bonus(null, $this->league('ES'), 0, 0));
        $this->assertSame(0, $this->bonus($this->league('EN'), null, 0, 0));
    }
}
