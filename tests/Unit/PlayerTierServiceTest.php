<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Player\Services\PlayerTierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerTierServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlayerTierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlayerTierService();
    }

    // -------------------------------------------------------
    // tierFromMarketValue — pure computation
    // -------------------------------------------------------

    public function test_tier_5_for_world_class_market_values(): void
    {
        $this->assertEquals(5, PlayerTierService::tierFromMarketValue(150_000_000));  // €1.5M exactly
        $this->assertEquals(5, PlayerTierService::tierFromMarketValue(200_000_000)); // €2M
        $this->assertEquals(5, PlayerTierService::tierFromMarketValue(175_000_000));  // €1.75M
    }

    public function test_tier_4_for_excellent_market_values(): void
    {
        $this->assertEquals(4, PlayerTierService::tierFromMarketValue(149_999_999)); // Just below €1.5M
        $this->assertEquals(4, PlayerTierService::tierFromMarketValue(80_000_000));  // €800K exactly
        $this->assertEquals(4, PlayerTierService::tierFromMarketValue(100_000_000)); // €1M
    }

    public function test_tier_3_for_good_market_values(): void
    {
        $this->assertEquals(3, PlayerTierService::tierFromMarketValue(79_999_999)); // Just below €800K
        $this->assertEquals(3, PlayerTierService::tierFromMarketValue(30_000_000)); // €300K exactly
        $this->assertEquals(3, PlayerTierService::tierFromMarketValue(50_000_000));  // €500K
    }

    public function test_tier_2_for_average_market_values(): void
    {
        $this->assertEquals(2, PlayerTierService::tierFromMarketValue(29_999_999)); // Just below €300K
        $this->assertEquals(2, PlayerTierService::tierFromMarketValue(10_000_000)); // €100K exactly
        $this->assertEquals(2, PlayerTierService::tierFromMarketValue(20_000_000)); // €200K
    }

    public function test_tier_1_for_developing_market_values(): void
    {
        $this->assertEquals(1, PlayerTierService::tierFromMarketValue(9_999_999));  // Just below €100K
        $this->assertEquals(1, PlayerTierService::tierFromMarketValue(0));          // Free / tournament
        $this->assertEquals(1, PlayerTierService::tierFromMarketValue(5_000_000));  // €50K
        $this->assertEquals(1, PlayerTierService::tierFromMarketValue(10_000));     // €100
    }

    // -------------------------------------------------------
    // recomputeTiers — batch SQL
    // -------------------------------------------------------

    public function test_recompute_tiers_updates_players_correctly(): void
    {
        $game = Game::factory()->create();
        $team = Team::factory()->create();

        $worldClass = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => 180_000_000, // €1.8M → tier 5
            'tier' => 1, // Wrong tier intentionally
        ]);

        $average = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => 20_000_000, // €200K → tier 2
            'tier' => 5, // Wrong tier intentionally
        ]);

        $this->service->recomputeTiers([$worldClass->id, $average->id]);

        $this->assertEquals(5, $worldClass->fresh()->tier);
        $this->assertEquals(2, $average->fresh()->tier);
    }

    public function test_recompute_tiers_only_affects_specified_ids(): void
    {
        $game = Game::factory()->create();
        $team = Team::factory()->create();

        $target = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => 160_000_000, // €1.6M → tier 5
            'tier' => 1,
        ]);

        $untouched = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => 160_000_000, // €1.6M → should be tier 5 but stays 1
            'tier' => 1,
        ]);

        $this->service->recomputeTiers([$target->id]);

        $this->assertEquals(5, $target->fresh()->tier);
        $this->assertEquals(1, $untouched->fresh()->tier); // Not updated
    }

    public function test_recompute_tiers_handles_empty_array(): void
    {
        // Should not throw or execute any queries
        $this->service->recomputeTiers([]);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------
    // Factory produces correct tier
    // -------------------------------------------------------

    public function test_factory_produces_tier_consistent_with_market_value(): void
    {
        $game = Game::factory()->create();
        $team = Team::factory()->create();

        $player = GamePlayer::factory()->forGame($game)->forTeam($team)->create();

        $expectedTier = PlayerTierService::tierFromMarketValue($player->market_value_cents);
        $this->assertEquals($expectedTier, $player->tier);
    }
}
