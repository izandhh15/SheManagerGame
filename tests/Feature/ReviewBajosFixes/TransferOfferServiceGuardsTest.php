<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * BAJA validación (hardening): ContractService::negotiateTermsSync y
 * TransferService::acceptOffer/rejectOffer/acceptIncomingOffer no
 * verificaban estado de la oferta ni propiedad oferta↔juego.
 */
class TransferOfferServiceGuardsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    private function makeOffer(array $overrides = []): TransferOffer
    {
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->team)->create();

        return TransferOffer::create(array_merge([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->team->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 1_000_000,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => $this->game->current_date,
            'game_date' => $this->game->current_date,
        ], $overrides));
    }

    public function test_negotiate_terms_sync_rejects_cross_game_offer_with_422(): void
    {
        $offer = $this->makeOffer();
        $otherGame = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        try {
            app(ContractService::class)->negotiateTermsSync(
                $offer,
                100_000,
                3,
                NegotiationScenario::TRANSFER,
                $otherGame
            );
            $this->fail('Expected HttpException 422');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        // La oferta no se ha tocado.
        $this->assertSame(TransferOffer::STATUS_PENDING, $offer->fresh()->status);
    }

    public function test_accept_offer_ignores_non_pending_offer(): void
    {
        $offer = $this->makeOffer(['status' => TransferOffer::STATUS_AGREED]);

        $result = app(TransferService::class)->acceptOffer($offer);

        $this->assertFalse($result);
        $this->assertSame(TransferOffer::STATUS_AGREED, $offer->fresh()->status);
    }

    public function test_reject_offer_ignores_already_rejected_offer(): void
    {
        $offer = $this->makeOffer(['status' => TransferOffer::STATUS_REJECTED]);

        app(TransferService::class)->rejectOffer($offer);

        $this->assertSame(TransferOffer::STATUS_REJECTED, $offer->fresh()->status);
    }

    public function test_reject_offer_still_rejects_pending_offer(): void
    {
        $offer = $this->makeOffer(['status' => TransferOffer::STATUS_PENDING]);

        app(TransferService::class)->rejectOffer($offer);

        $this->assertSame(TransferOffer::STATUS_REJECTED, $offer->fresh()->status);
    }

    public function test_accept_incoming_offer_ignores_non_reachable_status(): void
    {
        $offer = $this->makeOffer(['status' => TransferOffer::STATUS_AGREED]);

        $result = app(TransferService::class)->acceptIncomingOffer($offer);

        $this->assertFalse($result);
        $this->assertSame(TransferOffer::STATUS_AGREED, $offer->fresh()->status);
    }
}
