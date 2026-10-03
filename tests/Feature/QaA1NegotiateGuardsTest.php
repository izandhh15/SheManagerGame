<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA A1: `offer_terms` y `accept_terms_counter` deben repetir las
 * validaciones de `start` en NegotiateFreeAgent y NegotiatePreContract.
 *
 * Sin este fix, un POST directo (sin pasar por `start`) a
 * /game/{id}/negotiate/free-agent/{playerId} con action=offer_terms
 * fichaba GRATIS a cualquier jugadora con contrato (fee 0, presupuesto
 * intacto). Lo mismo en el endpoint de precontratos (sin comprobar
 * ventana, expiración ni reputación).
 */
class QaA1NegotiateGuardsTest extends TestCase
{
    use RefreshDatabase;

    private const BUDGET = 100_000_000_00; // €100M en céntimos

    private User $user;
    private Team $userTeam;
    private Team $sellerTeam;
    private Game $game;
    private TransferService $transferService;
    private ContractService $contractService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'QA A1 User Team', 'country' => 'ES']);
        $this->sellerTeam = Team::factory()->create(['name' => 'QA A1 Seller Team', 'country' => 'ES']);

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LigaTest']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2025',
            'current_date' => '2025-08-15',
            'release_clauses_enabled' => true,
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => $this->game->season,
            'transfer_budget' => self::BUDGET,
        ]);

        GamePlayer::factory()->count(20)->create([
            'game_id' => $this->game->id,
            'team_id' => $this->sellerTeam->id,
        ]);

        $this->transferService = app(TransferService::class);
        $this->contractService = app(ContractService::class);
    }

    private function contractedPlayer(array $overrides = []): GamePlayer
    {
        return GamePlayer::factory()->create(array_merge([
            'game_id' => $this->game->id,
            'team_id' => $this->sellerTeam->id,
            'tier' => 2,
            'market_value_cents' => 2_000_000_00,
            'annual_wage' => 150_000_00,
            'contract_until' => '2027-06-30',
        ], $overrides));
    }

    private function budget(): int
    {
        return GameInvestment::where('game_id', $this->game->id)
            ->where('season', $this->game->season)
            ->value('transfer_budget');
    }

    /** Salario generoso (3x demanda) en euros: la negociación síncrona lo acepta de forma determinista. */
    private function generousWageEuros(GamePlayer $player, NegotiationScenario $scenario): int
    {
        $demand = $this->contractService->calculateWageDemand($player, $scenario, $this->userTeam);

        return (int) ($demand['wage'] * 3 / 100);
    }

    private function seedCounteredOffer(GamePlayer $player, string $offerType, array $overrides = []): TransferOffer
    {
        return TransferOffer::create(array_merge([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $offerType === TransferOffer::TYPE_PRE_CONTRACT ? $this->sellerTeam->id : null,
            'offer_type' => $offerType,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_PENDING,
            'terms_status' => 'countered',
            'terms_round' => 1,
            'offered_wage' => 100_000_00,
            'player_demand' => 200_000_00,
            'wage_counter_offer' => 200_000_00,
            'preferred_years' => 3,
            'expires_at' => $this->game->current_date,
            'game_date' => $this->game->current_date,
        ], $overrides));
    }

    public function test_free_agent_offer_terms_rejects_contracted_player(): void
    {
        $player = $this->contractedPlayer();

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.free-agent', [$this->game->id, $player->id]),
            ['action' => 'offer_terms', 'wage' => $this->generousWageEuros($player, NegotiationScenario::FREE_AGENT), 'years' => 3]
        );

        $response->assertStatus(422);
        $this->assertSame(0, TransferOffer::where('game_player_id', $player->id)->count(), 'No debe crearse ninguna oferta');
        $this->assertSame(self::BUDGET, $this->budget(), 'El presupuesto debe quedar intacto');
        $this->assertSame($this->sellerTeam->id, $player->fresh()->team_id, 'La jugadora no debe cambiar de equipo');
    }

    public function test_free_agent_accept_terms_counter_rejects_contracted_player(): void
    {
        $player = $this->contractedPlayer();
        $offer = $this->seedCounteredOffer($player, TransferOffer::TYPE_USER_BID);

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.free-agent', [$this->game->id, $player->id]),
            ['action' => 'accept_terms_counter']
        );

        $response->assertStatus(422);
        $this->assertSame(TransferOffer::STATUS_PENDING, $offer->fresh()->status, 'La oferta no debe aceptarse');
        $this->assertSame($this->sellerTeam->id, $player->fresh()->team_id);
        $this->assertSame(self::BUDGET, $this->budget());
    }

    public function test_precontract_offer_terms_rejects_outside_period(): void
    {
        // Agosto: fuera del periodo de precontratos (enero-mayo).
        $player = $this->contractedPlayer(['contract_until' => '2026-06-30']);

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.pre-contract', [$this->game->id, $player->id]),
            ['action' => 'offer_terms', 'wage' => $this->generousWageEuros($player, NegotiationScenario::PRE_CONTRACT), 'years' => 4]
        );

        $response->assertStatus(422);
        $this->assertSame(0, TransferOffer::where('game_player_id', $player->id)->count());
        $this->assertSame(self::BUDGET, $this->budget());
    }

    public function test_precontract_offer_terms_rejects_non_expiring_contract(): void
    {
        // Enero (dentro del periodo) pero contrato hasta 2028: no expira.
        $this->game->update(['current_date' => '2026-01-15']);
        $this->game->refresh();
        $player = $this->contractedPlayer(['contract_until' => '2028-06-30']);

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.pre-contract', [$this->game->id, $player->id]),
            ['action' => 'offer_terms', 'wage' => $this->generousWageEuros($player, NegotiationScenario::PRE_CONTRACT), 'years' => 4]
        );

        $response->assertStatus(422);
        $this->assertSame(0, TransferOffer::where('game_player_id', $player->id)->count());
        $this->assertSame(self::BUDGET, $this->budget());
    }

    public function test_precontract_accept_terms_counter_rejects_outside_period(): void
    {
        $player = $this->contractedPlayer(['contract_until' => '2026-06-30']);
        $offer = $this->seedCounteredOffer($player, TransferOffer::TYPE_PRE_CONTRACT);

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.pre-contract', [$this->game->id, $player->id]),
            ['action' => 'accept_terms_counter']
        );

        $response->assertStatus(422);
        $this->assertSame(TransferOffer::STATUS_PENDING, $offer->fresh()->status);
        $this->assertSame(self::BUDGET, $this->budget());
    }

    public function test_free_agent_legitimate_flow_still_works(): void
    {
        // Agente libre REAL (team_id null): start -> offer_terms debe funcionar.
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => null,
            'tier' => 2,
            'market_value_cents' => 2_000_000_00,
            'annual_wage' => 150_000_00,
        ]);

        $start = $this->actingAs($this->user)->postJson(
            route('game.negotiate.free-agent', [$this->game->id, $player->id]),
            ['action' => 'start']
        );
        $start->assertOk();
        $start->assertJson(['negotiation_status' => 'terms_open']);

        $terms = $this->actingAs($this->user)->postJson(
            route('game.negotiate.free-agent', [$this->game->id, $player->id]),
            ['action' => 'offer_terms', 'wage' => $this->generousWageEuros($player, NegotiationScenario::FREE_AGENT), 'years' => 3]
        );
        $terms->assertOk();
        $terms->assertJson(['negotiation_status' => 'completed']);

        $this->transferService->completeIncomingTransfers($this->game);

        $this->assertSame($this->userTeam->id, $player->fresh()->team_id, 'La agente libre debe unirse al equipo');
        $this->assertSame(self::BUDGET, $this->budget(), 'Un agente libre no debe tocar el presupuesto');

        $transfer = GameTransfer::where('game_player_id', $player->id)->first();
        $this->assertNotNull($transfer);
        $this->assertSame(GameTransfer::TYPE_FREE_AGENT, $transfer->type);
        $this->assertSame(0, (int) $transfer->transfer_fee);
        $this->assertNull($transfer->from_team_id);
        $this->assertSame($this->userTeam->id, $transfer->to_team_id);
    }

    public function test_precontract_legitimate_flow_offer_terms_passes_guards(): void
    {
        // Enero, contrato que expira a final de temporada, tier bajo (pasa el
        // gate de reputación): el flujo legítimo debe seguir funcionando.
        $this->game->update(['current_date' => '2026-01-15']);
        $this->game->refresh();
        $player = $this->contractedPlayer([
            'tier' => 2,
            'market_value_cents' => 1_000_000_00,
            'annual_wage' => 100_000_00,
            'contract_until' => '2026-06-30',
        ]);

        $start = $this->actingAs($this->user)->postJson(
            route('game.negotiate.pre-contract', [$this->game->id, $player->id]),
            ['action' => 'start']
        );
        $start->assertOk();
        $start->assertJson(['negotiation_status' => 'terms_open']);

        $terms = $this->actingAs($this->user)->postJson(
            route('game.negotiate.pre-contract', [$this->game->id, $player->id]),
            ['action' => 'offer_terms', 'wage' => $this->generousWageEuros($player, NegotiationScenario::PRE_CONTRACT), 'years' => 4]
        );
        $terms->assertOk();
        $terms->assertJson(['negotiation_status' => 'completed']);

        $offer = TransferOffer::where('game_player_id', $player->id)->first();
        $this->assertNotNull($offer, 'Debe haberse creado la oferta de precontrato');
        $this->assertSame(TransferOffer::TYPE_PRE_CONTRACT, $offer->offer_type);
        $this->assertSame(self::BUDGET, $this->budget(), 'El precontrato no debe tocar el presupuesto');
    }
}
