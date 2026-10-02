<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameSponsorDeal;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Commercial\Services\SponsorOfferFactory;
use App\Modules\Commercial\Services\SponsorService;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

/**
 * Sponsor deals (shirt + ad boards): offers arrive monthly with a stature
 * tier driven by division + league position — bottom of the second tier
 * gets the local university, top of the first tier gets the big brands.
 * Accepting pays the money and fills the slot; rejecting discards the bid.
 */
class SponsorDealsTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private SponsorService $service;
    private Competition $ligaF;   // tier 1
    private Competition $primeraRfef; // tier 2

    protected function setUp(): void
    {
        parent::setUp();

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('create')->byDefault();

        $this->service = new SponsorService(new SponsorOfferFactory(), $notifications);

        $this->ligaF = Competition::factory()->league()->create(['tier' => 1]);
        $this->primeraRfef = Competition::factory()->league()->create(['tier' => 2]);
    }

    // ── (a) bottom of the second tier → local sponsors ───────────────────

    public function test_bottom_of_segunda_gets_local_tier_offers(): void
    {
        $game = $this->leagueGame($this->primeraRfef, teamPosition: 16, teamCount: 16);

        $this->assertSame(GameSponsorDeal::TIER_LOCAL, $this->service->resolveSponsorTier($game));

        $minted = $this->service->generateMonthlyOffers($game);

        // 3 pending offers per slot (shirt + ad boards).
        $this->assertCount(6, $minted);

        foreach ($minted as $offer) {
            $this->assertSame(GameSponsorDeal::TIER_LOCAL, $offer->tier);

            [, $max] = $this->band($offer->slot, 'local');
            // Term multiplier can discount below the band, never above it.
            $this->assertLessThanOrEqual($max, $offer->annual_value_cents);
            $this->assertGreaterThan(0, $offer->annual_value_cents);
        }

        // The small club gets neighbourhood names, not global giants: every
        // sponsor must come from the local pool (universities, local shops).
        $localNames = $this->localPoolNames('ES');
        foreach ($minted as $offer) {
            $this->assertContains($offer->sponsor_name, $localNames, "Unexpected sponsor for a bottom-tier club: {$offer->sponsor_name}");
        }
    }

    // ── (b) top of the first tier → international sponsors ───────────────

    public function test_leader_of_liga_f_gets_international_tier_offers(): void
    {
        $game = $this->leagueGame($this->ligaF, teamPosition: 1, teamCount: 16);

        $this->assertSame(GameSponsorDeal::TIER_INTERNACIONAL, $this->service->resolveSponsorTier($game));

        $minted = $this->service->generateMonthlyOffers($game);
        $this->assertCount(6, $minted);

        foreach ($minted as $offer) {
            $this->assertSame(GameSponsorDeal::TIER_INTERNACIONAL, $offer->tier);
        }

        // Big money for the big stage: the shirt headline beats anything a
        // local sponsor could ever pay.
        $shirtOffers = array_filter($minted, fn ($o) => $o->slot === GameSponsorDeal::SLOT_SHIRT);
        $topShirt = max(array_map(fn ($o) => $o->annual_value_cents, $shirtOffers));
        $this->assertGreaterThanOrEqual(150_000_00, $topShirt);

        // A second monthly top-up in the same month mints nothing.
        $this->assertSame([], $this->service->generateMonthlyOffers($game));
    }

    public function test_mid_table_segunda_gets_regional_tier(): void
    {
        $game = $this->leagueGame($this->primeraRfef, teamPosition: 8, teamCount: 16);

        $this->assertSame(GameSponsorDeal::TIER_REGIONAL, $this->service->resolveSponsorTier($game));
    }

    // ── (c) accepting pays the money and fills the slot ──────────────────

    public function test_accepting_an_offer_pays_the_money_and_fills_the_slot(): void
    {
        $game = $this->leagueGame($this->ligaF, teamPosition: 1, teamCount: 16);
        GameFinances::create(['game_id' => $game->id, 'season' => 2026]);

        $offer = $this->seedOffer($game, GameSponsorDeal::SLOT_SHIRT, value: 2_000_000_00, seasons: 2);
        $rival = $this->seedOffer($game, GameSponsorDeal::SLOT_SHIRT, value: 1_500_000_00);
        $boardOffer = $this->seedOffer($game, GameSponsorDeal::SLOT_AD_BOARD, value: 500_000_00);

        $deal = $this->service->acceptOffer($game, $offer->id);

        // The deal is active for its term.
        $this->assertSame(GameSponsorDeal::STATUS_ACTIVE, $deal->fresh()->status);
        $this->assertSame(2026, $deal->fresh()->start_season);
        $this->assertSame(2027, $deal->fresh()->end_season);

        // The money lands in the projected finances (and the surplus).
        $finances = GameFinances::where('game_id', $game->id)->firstOrFail();
        $this->assertSame(2_000_000_00, (int) $finances->projected_shirt_sponsor_revenue);
        $this->assertSame(2_000_000_00, (int) $finances->projected_total_revenue);
        $this->assertSame(2_000_000_00, (int) $finances->projected_surplus);

        // Competing shirt offers are rejected; the ad-board slot is untouched.
        $this->assertSame(GameSponsorDeal::STATUS_REJECTED, $rival->fresh()->status);
        $this->assertSame(GameSponsorDeal::STATUS_PENDING, $boardOffer->fresh()->status);

        // The slot is filled: a fresh shirt offer can't be signed while one is active.
        $fresh = $this->seedOffer($game, GameSponsorDeal::SLOT_SHIRT, value: 1_000_000_00);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.sponsor_deal_active');
        $this->service->acceptOffer($game, $fresh->id);
    }

    public function test_settled_revenue_is_the_fixed_annual_fee(): void
    {
        $game = $this->leagueGame($this->ligaF, teamPosition: 1, teamCount: 16);
        $deal = $this->seedOffer($game, GameSponsorDeal::SLOT_AD_BOARD, status: GameSponsorDeal::STATUS_ACTIVE, value: 750_000_00);
        $deal->update(['start_season' => 2026, 'end_season' => 2028]);

        $this->assertSame(750_000_00, $this->service->settledRevenueForGame($game, GameSponsorDeal::SLOT_AD_BOARD));
        $this->assertSame(750_000_00, $this->service->projectedRevenueForGame($game, GameSponsorDeal::SLOT_AD_BOARD));
        $this->assertSame(0, $this->service->projectedRevenueForGame($game, GameSponsorDeal::SLOT_SHIRT));
    }

    // ── (d) rejecting discards the offer ─────────────────────────────────

    public function test_rejecting_an_offer_discards_it(): void
    {
        $game = $this->leagueGame($this->primeraRfef, teamPosition: 16, teamCount: 16);
        GameFinances::create(['game_id' => $game->id, 'season' => 2026]);

        $offer = $this->seedOffer($game, GameSponsorDeal::SLOT_SHIRT, value: 2_000_00);
        $other = $this->seedOffer($game, GameSponsorDeal::SLOT_SHIRT, value: 1_800_00);

        $rejected = $this->service->rejectOffer($game, $offer->id);

        $this->assertSame(GameSponsorDeal::STATUS_REJECTED, $rejected->fresh()->status);

        // No money moved, no slot filled, the other offer is still there.
        $finances = GameFinances::where('game_id', $game->id)->firstOrFail();
        $this->assertSame(0, (int) $finances->projected_shirt_sponsor_revenue);
        $this->assertNull(GameSponsorDeal::activeForGameSlot($game->id, $game->team_id, GameSponsorDeal::SLOT_SHIRT));
        $this->assertSame(GameSponsorDeal::STATUS_PENDING, $other->fresh()->status);

        // Rejecting twice (or a non-pending offer) fails loudly.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.sponsor_offer_unavailable');
        $this->service->rejectOffer($game, $offer->id);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * A game in a league competition with a full standings table, the
     * player's team at the given position.
     */
    private function leagueGame(Competition $league, int $teamPosition, int $teamCount): Game
    {
        $team = Team::factory()->create();
        $game = Game::factory()->forTeam($team)->inCompetition($league->id)
            ->create(['pre_season' => false, 'season' => 2026, 'current_date' => '2026-10-02', 'country' => 'ES']);

        $rivals = Team::factory()->count($teamCount - 1)->create();
        $position = 1;
        foreach ($rivals as $rival) {
            if ($position === $teamPosition) {
                $position++;
            }
            GameStanding::create([
                'game_id' => $game->id,
                'competition_id' => $league->id,
                'team_id' => $rival->id,
                'position' => $position++,
                'points' => 0,
            ]);
        }

        GameStanding::create([
            'game_id' => $game->id,
            'competition_id' => $league->id,
            'team_id' => $team->id,
            'position' => $teamPosition,
            'points' => 0,
        ]);

        return $game;
    }

    private function seedOffer(
        Game $game,
        string $slot,
        string $status = GameSponsorDeal::STATUS_PENDING,
        int $value = 1_000_000_00,
        int $seasons = 3,
    ): GameSponsorDeal {
        static $i = 0;
        $i++;

        return GameSponsorDeal::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'slot' => $slot,
            'sponsor_name' => "Test Sponsor {$i}",
            'tier' => GameSponsorDeal::TIER_LOCAL,
            'annual_value_cents' => $value,
            'contract_seasons' => $seasons,
            'status' => $status,
            'offered_season' => 2026,
            'offered_at' => '2026-09-15 12:00:00',
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function band(string $slot, string $tier): array
    {
        $range = config("commercial.sponsor_deals.annual_value.{$slot}.{$tier}");

        return [(int) $range[0], (int) $range[1]];
    }

    /**
     * @return array<int, string>
     */
    private function localPoolNames(string $country): array
    {
        $names = [];
        foreach ((array) config("commercial.sponsor_deals.local_sponsors.{$country}", []) as $sponsor) {
            $names[] = $sponsor['name'];
        }

        return $names;
    }
}
