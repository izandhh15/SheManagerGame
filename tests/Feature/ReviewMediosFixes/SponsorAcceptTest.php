<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Game;
use App\Models\GameSponsorDeal;
use App\Models\Team;
use App\Models\User;
use App\Modules\Commercial\Services\SponsorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fix 9: SponsorService::acceptOffer() runs in a transaction — accepting
 * activates the deal, rejects the competing offers for the slot and
 * refreshes the projections atomically.
 */
class SponsorAcceptTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    private function offer(string $slot, string $status = GameSponsorDeal::STATUS_PENDING): GameSponsorDeal
    {
        return GameSponsorDeal::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'team_id' => $this->game->team_id,
            'slot' => $slot,
            'sponsor_name' => 'Test Sponsor ' . Str::random(4),
            'tier' => GameSponsorDeal::TIER_LOCAL,
            'annual_value_cents' => 1_000_000_00,
            'contract_seasons' => 2,
            'status' => $status,
            'offered_season' => 2026,
        ]);
    }

    public function test_accept_activates_deal_and_rejects_competitors(): void
    {
        $service = app(SponsorService::class);
        $winner = $this->offer(GameSponsorDeal::SLOT_SHIRT);
        $loser = $this->offer(GameSponsorDeal::SLOT_SHIRT);
        $otherSlot = $this->offer(GameSponsorDeal::SLOT_AD_BOARD);

        $deal = $service->acceptOffer($this->game, $winner->id);

        $this->assertSame(GameSponsorDeal::STATUS_ACTIVE, $deal->fresh()->status);
        $this->assertSame(2026, (int) $deal->fresh()->start_season);
        $this->assertSame(2027, (int) $deal->fresh()->end_season);
        $this->assertSame(GameSponsorDeal::STATUS_REJECTED, $loser->fresh()->status);
        // The other slot is untouched.
        $this->assertSame(GameSponsorDeal::STATUS_PENDING, $otherSlot->fresh()->status);
    }

    public function test_accept_twice_throws(): void
    {
        $service = app(SponsorService::class);
        $winner = $this->offer(GameSponsorDeal::SLOT_SHIRT);

        $service->acceptOffer($this->game, $winner->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->acceptOffer($this->game, $winner->id);
    }
}
