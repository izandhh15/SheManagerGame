<?php

namespace Tests\Feature\QaBajosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\MutualTerminationNegotiation;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Models\UserSquadCareerRecord;
use App\Modules\Finance\Services\SeverancePaymentService;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA bajos, agente A: bugs B1, B2, B3 (traspasos/rescisiones/cesiones).
 *
 * Repros adaptados de ~/workspace/shemanager/qa/workers/agent-6/
 * Agent6MutualTerminationTest.php.
 */
class AgentAReleaseFixesTest extends TestCase
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

    private function pendingSaleOffer(GamePlayer $player): TransferOffer
    {
        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->aiTeam->id,
            'selling_team_id' => $this->userTeam->id,
            'offer_type' => TransferOffer::TYPE_LISTED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => 8_000_000_00,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => '2026-08-20',
            'game_date' => '2026-08-01',
        ]);
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

    private function completeTermination(GamePlayer $player)
    {
        return $this->actingAs($this->user)->post(
            route('game.squad.mutual-termination.complete', [$this->game->id, $player->id]),
            ['payment_method' => SeverancePaymentService::METHOD_LUMP_SUM]
        );
    }

    // ---------------- B1: rescindir expira las ofertas de venta pendientes

    public function test_release_expires_pending_sale_offers(): void
    {
        $this->fillFirstTeam(18);
        $player = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)->where('number', 10)->firstOrFail();

        $offer = $this->pendingSaleOffer($player);

        app(ContractService::class)->releasePlayer($this->game, $player);

        $this->assertSame(
            TransferOffer::STATUS_EXPIRED,
            $offer->fresh()->status,
            'Rescindir debe expirar las ofertas pendientes de la jugadora, no dejarlas colgadas.'
        );
        $this->assertNull($player->fresh()->team_id);
    }

    public function test_mutual_termination_expires_pending_sale_offers(): void
    {
        $this->fillFirstTeam(18);
        $player = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)->where('number', 10)->firstOrFail();

        $offer = $this->pendingSaleOffer($player);
        $this->agreeMutualTermination($player);

        $response = $this->completeTermination($player);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(
            TransferOffer::STATUS_EXPIRED,
            $offer->fresh()->status,
            'El mutuo acuerdo completado debe expirar las ofertas pendientes igual que la rescisión unilateral.'
        );
        $this->assertNull($player->fresh()->team_id);
    }

    // ---------------- B2: completeLoanOut registra el equipo real de la jugadora

    public function test_loan_service_complete_loan_out_keeps_true_parent_team(): void
    {
        $reserveTeam = Team::factory()->create(['name' => 'Reserve Team']);
        $this->game->update(['reserve_team_id' => $reserveTeam->id]);
        $this->fillFirstTeam(18);

        $reservePlayer = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $reserveTeam->id,
            'position' => 'Central Midfield',
            'number' => 8,
            'market_value_cents' => 5_000_000_00,
            'contract_until' => '2028-06-30',
            'annual_wage' => 1_000_000_00,
        ]);

        $offer = TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $reservePlayer->id,
            'offering_team_id' => $this->aiTeam->id,
            'selling_team_id' => $reserveTeam->id,
            'offer_type' => TransferOffer::TYPE_LOAN_OUT,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => '2026-08-20',
            'game_date' => '2026-08-01',
        ]);

        app(LoanService::class)->completeLoanOut($offer, $this->game);

        $loan = Loan::where('game_id', $this->game->id)
            ->where('game_player_id', $reservePlayer->id)->firstOrFail();

        $this->assertSame($reserveTeam->id, $loan->parent_team_id,
            'El préstamo debe registrar el filial como club de origen, no el primer equipo.');
        $this->assertSame($this->aiTeam->id, $loan->loan_team_id);
    }

    // ---------------- B3: rescindir elimina el UserSquadCareerRecord

    public function test_release_removes_career_record_like_sale_does(): void
    {
        $this->fillFirstTeam(18);
        $player = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)->where('number', 10)->firstOrFail();

        UserSquadCareerRecord::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'team_id' => $this->userTeam->id,
            'joined_season' => 2026,
            'joined_from' => 'Academy',
        ]);

        app(ContractService::class)->releasePlayer($this->game, $player);

        $this->assertNull(
            UserSquadCareerRecord::where('game_player_id', $player->id)->first(),
            'Al rescindir, el career record debe eliminarse igual que en una venta.'
        );
    }

    public function test_mutual_termination_removes_career_record_like_sale_does(): void
    {
        $this->fillFirstTeam(18);
        $player = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)->where('number', 10)->firstOrFail();

        UserSquadCareerRecord::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'team_id' => $this->userTeam->id,
            'joined_season' => 2026,
            'joined_from' => 'Academy',
        ]);

        $this->agreeMutualTermination($player);

        $response = $this->completeTermination($player);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertNull(
            UserSquadCareerRecord::where('game_player_id', $player->id)->first(),
            'Al completar el mutuo acuerdo, el career record debe eliminarse igual que en una venta.'
        );
    }
}
