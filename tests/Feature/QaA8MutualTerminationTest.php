<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\MutualTerminationNegotiation;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Finance\Services\SeverancePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA fase 2 — bug A8 (alto): TOCTOU en rescisión de mutuo acuerdo.
 *
 * Reproduce: se pacta un mutuo acuerdo (AGREED) sin completarlo y
 * entremedias se acepta una oferta de venta por la misma jugadora.
 * Completar la rescisión debe abortar SIN cobrar indemnización, SIN
 * liberar a la jugadora y SIN tocar la oferta (que sigue en AGREED y
 * podrá completarse en el avance de jornada).
 */
class QaA8MutualTerminationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $userTeam;
    private Team $aiTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team']);
        $this->aiTeam = Team::factory()->create(['name' => 'AI Buyer']);

        $competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => $competition->id,
            'current_date' => '2026-08-01',
            'season' => '2026',
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => 2026,
            'transfer_budget' => 50_000_000_00,
            'scouting_tier' => 1,
        ]);
    }

    private function fillFirstTeam(int $size = 18): void
    {
        for ($i = 1; $i <= $size; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->userTeam->id,
                'position' => $i <= 2 ? 'Goalkeeper' : 'Central Midfield',
                'number' => $i,
                'market_value_cents' => 5_000_000_00,
                'contract_until' => '2028-06-30',
                'annual_wage' => 1_000_000_00,
            ]);
        }
    }

    private function squadCount(): int
    {
        return GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)->count();
    }

    private function transferBudget(): int
    {
        return (int) GameInvestment::where('game_id', $this->game->id)
            ->where('season', 2026)->value('transfer_budget');
    }

    private function agreeMutualTermination(GamePlayer $player, int $amountCents = 500_000_00): MutualTerminationNegotiation
    {
        return MutualTerminationNegotiation::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'status' => MutualTerminationNegotiation::STATUS_AGREED,
            'round' => 1,
            'agent_demand' => $amountCents,
            'agreed_amount' => $amountCents,
        ]);
    }

    private function agreeSaleOffer(GamePlayer $player, int $feeCents = 8_000_000_00): TransferOffer
    {
        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->aiTeam->id,
            'selling_team_id' => $this->userTeam->id,
            'offer_type' => TransferOffer::TYPE_LISTED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => $feeCents,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => '2026-08-20',
            'game_date' => '2026-08-01',
        ]);
    }

    private function completeTermination(GamePlayer $player)
    {
        return $this->actingAs($this->user)->post(
            route('game.squad.mutual-termination.complete', [$this->game->id, $player->id]),
            ['payment_method' => SeverancePaymentService::METHOD_LUMP_SUM]
        );
    }

    /**
     * A8: venta aceptada (AGREED) entre el pacto y el completar → la
     * rescisión se aborta con error, sin indemnización, sin liberar a la
     * jugadora y con la oferta intacta en AGREED.
     */
    public function test_completion_blocked_when_sale_agreed_afterwards(): void
    {
        $this->fillFirstTeam(18);
        $player = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)->where('number', 10)->firstOrFail();

        // 1) Se pacta el mutuo acuerdo (€500k) pero NO se completa.
        $negotiation = $this->agreeMutualTermination($player, 500_000_00);

        // 2) Entremedias se acepta una oferta de venta de €8M.
        $offer = $this->agreeSaleOffer($player, 8_000_000_00);

        $budgetBefore = $this->transferBudget();

        // 3) Completar la rescisión debe abortar.
        $response = $this->completeTermination($player);

        $response->assertRedirect();
        $response->assertSessionHas('error', __('messages.release_has_agreed_transfer'));

        // La jugadora sigue en la plantilla (la venta podrá completarse).
        $this->assertSame($this->userTeam->id, $player->fresh()->team_id);
        $this->assertSame(10, $player->fresh()->number);
        $this->assertSame(18, $this->squadCount());

        // La oferta queda intacta en AGREED: nada la huérfana.
        $this->assertSame(TransferOffer::STATUS_AGREED, $offer->fresh()->status);

        // La negociación del mutuo acuerdo sigue AGREED (no se completó).
        $this->assertSame(
            MutualTerminationNegotiation::STATUS_AGREED,
            $negotiation->fresh()->status
        );

        // No se cobra la indemnización: presupuesto intacto.
        $this->assertSame($budgetBefore, $this->transferBudget());
    }

    /**
     * A8(c): con la plantilla justo en el mínimo (17), completar el mutuo
     * acuerdo debe bloquearse como la rescisión unilateral.
     */
    public function test_completion_blocked_below_squad_minimum(): void
    {
        $this->fillFirstTeam(17); // justo en el mínimo
        $player = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)->where('number', 5)->firstOrFail();

        $this->agreeMutualTermination($player, 500_000_00);
        $budgetBefore = $this->transferBudget();

        $response = $this->completeTermination($player);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($this->userTeam->id, $player->fresh()->team_id);
        $this->assertSame(17, $this->squadCount());
        $this->assertSame($budgetBefore, $this->transferBudget());
    }

    /**
     * A8: el flujo normal de mutuo acuerdo (sin venta de por medio) sigue
     * funcionando: se cobra la indemnización pactada y la jugadora queda libre.
     */
    public function test_completion_happy_path_still_works(): void
    {
        $this->fillFirstTeam(18);
        $player = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)->where('number', 10)->firstOrFail();

        $negotiation = $this->agreeMutualTermination($player, 500_000_00);
        $budgetBefore = $this->transferBudget();

        $response = $this->completeTermination($player);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');

        $player->refresh();
        $this->assertNull($player->team_id);
        $this->assertNull($player->number);
        $this->assertNull($player->release_clause);
        $this->assertSame(17, $this->squadCount());

        $this->assertSame(
            MutualTerminationNegotiation::STATUS_COMPLETED,
            $negotiation->fresh()->status
        );

        $this->assertSame($budgetBefore - 500_000_00, $this->transferBudget(),
            'La indemnización pactada debe descontarse del presupuesto.');
    }
}
