<?php

namespace Tests\Feature\QaMediumFixes;

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
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M3: un precontrato ENTRANTE se registraba en `game_transfers`
 * como `transfer` en vez de `free_agent`.
 *
 * Bug original: TransferService::completeIncomingPreContracts() delega en
 * TransferCompletionService::completeIncomingTransfer(), que grababa siempre
 * GameTransfer::TYPE_TRANSFER. El camino simétrico de salida
 * (completePreContractTransfer()) sí usaba TYPE_FREE_AGENT. El historial de
 * traspasos mostraba el fichaje libre como un traspaso de €0.
 *
 * Fix: completeIncomingTransfer() registra TYPE_FREE_AGENT cuando la oferta
 * es un precontrato ($offer->isPreContract()); las pujas con traspaso siguen
 * registrando TYPE_TRANSFER.
 */
class M3PreContractTransferTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $userTeam;
    private Team $sellerTeam;
    private Game $game;
    private TransferService $transferService;
    private ContractService $contractService;
    private ScoutingService $scoutingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'M3 User Team', 'country' => 'ES']);
        $this->sellerTeam = Team::factory()->create(['name' => 'M3 Seller Team', 'country' => 'ES']);

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
            'transfer_budget' => 100_000_000_00,
        ]);

        $this->transferService = app(TransferService::class);
        $this->contractService = app(ContractService::class);
        $this->scoutingService = app(ScoutingService::class);
    }

    private function aiPlayer(array $overrides = []): GamePlayer
    {
        return GamePlayer::factory()->create(array_merge([
            'game_id' => $this->game->id,
            'team_id' => $this->sellerTeam->id,
            'market_value_cents' => 10_000_000_00,
            'annual_wage' => 500_000_00,
        ], $overrides));
    }

    /**
     * Un precontrato entrante completado a fin de temporada debe quedar
     * registrado como free_agent (fichaje libre), no como transfer.
     */
    public function test_incoming_pre_contract_records_free_agent_type(): void
    {
        // Enero: periodo de precontratos (el contrato expira el 30-06-2026).
        $this->game->update(['current_date' => '2026-01-15']);
        $this->game->refresh();

        $player = $this->aiPlayer([
            'market_value_cents' => 1_000_000_00,
            'tier' => 2, // la factory no recalcula tier al sobreescribir market_value_cents
            'contract_until' => '2026-06-30',
            'annual_wage' => 100_000_00,
        ]);

        $demand = $this->contractService->calculateWageDemand($player, NegotiationScenario::PRE_CONTRACT, $this->userTeam);
        $offer = $this->transferService->submitPreContractOffer($this->game, $player, $demand['wage'] * 5);
        $this->assertSame(TransferOffer::STATUS_PENDING, $offer->status);

        // La respuesta asíncrona es probabilística por diseño; simulamos la
        // aceptación y probamos el completion, que es lo determinista.
        $offer->transitionTo(TransferOffer::STATUS_AGREED, $this->game->current_date);

        $this->game->update(['current_date' => '2026-06-30']);
        $this->game->refresh();
        $completed = $this->transferService->completeIncomingPreContracts($this->game);
        $this->assertCount(1, $completed);

        $this->assertSame($this->userTeam->id, $player->fresh()->team_id);

        $transfer = GameTransfer::where('game_player_id', $player->id)->first();
        $this->assertNotNull($transfer, 'Debe existir registro en game_transfers');
        $this->assertSame(0, (int) $transfer->transfer_fee);
        $this->assertSame($this->sellerTeam->id, $transfer->from_team_id);
        $this->assertSame($this->userTeam->id, $transfer->to_team_id);
        $this->assertSame(
            GameTransfer::TYPE_FREE_AGENT,
            $transfer->type,
            'Un precontrato entrante es un fichaje libre: debe registrarse como free_agent, no como transfer'
        );
    }

    /**
     * Guardia: un fichaje con traspaso pagado sigue registrándose como
     * `transfer` (el fix no debe convertirlo todo en free_agent).
     */
    public function test_paid_incoming_transfer_still_records_transfer_type(): void
    {
        // La plantilla del vendedor necesita tamaño suficiente para que la
        // venta sea aceptable.
        GamePlayer::factory()->count(20)->create([
            'game_id' => $this->game->id,
            'team_id' => $this->sellerTeam->id,
        ]);

        $player = $this->aiPlayer();
        $asking = $this->scoutingService->calculateAskingPrice($player, $this->game->current_date);

        $result = $this->transferService->negotiateTransferFeeSync($this->game, $player, (int) ($asking * 1.5), $this->scoutingService);
        $this->assertSame('accepted', $result['result']);

        $offer = $result['offer'];
        $demand = $this->contractService->calculateWageDemand($player, NegotiationScenario::TRANSFER, $this->userTeam);
        $terms = $this->contractService->negotiateTermsSync($offer, $demand['wage'] * 5, 4, NegotiationScenario::TRANSFER, $this->game);
        $this->assertSame('accepted', $terms['result']);

        $agreed = $terms['offer']->fresh();
        $this->transferService->acceptIncomingOffer($agreed);
        $this->transferService->completeIncomingTransfers($this->game);

        $transfer = GameTransfer::where('game_player_id', $player->id)->first();
        $this->assertNotNull($transfer);
        $this->assertGreaterThan(0, (int) $transfer->transfer_fee);
        $this->assertSame(GameTransfer::TYPE_TRANSFER, $transfer->type);
    }
}
