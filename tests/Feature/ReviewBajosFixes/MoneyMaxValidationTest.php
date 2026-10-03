<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA validación/32-bit: los importes monetarios tenían min:1 sin max
 * y el *100 a céntimos desbordaba a float en PHP 32-bit. Ahora
 * max:99999999.
 */
class MoneyMaxValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $userTeam;
    private Team $sellerTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $this->sellerTeam = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    public function test_transfer_bid_above_max_rejected(): void
    {
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->sellerTeam)->create();

        $this->actingAs($this->user)->postJson(
            route('game.negotiate.transfer', [$this->game->id, $player->id]),
            ['action' => 'offer', 'bid' => 100_000_000]
        )->assertStatus(422)->assertJsonValidationErrors('bid');
    }

    public function test_transfer_bid_at_max_passes_validation(): void
    {
        // Jugadora cedida: el guard de elegibilidad responde 422 con su
        // mensaje, lo que demuestra que la validación del importe pasó.
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->sellerTeam)->create();
        Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $this->sellerTeam->id,
            'loan_team_id' => $this->userTeam->id,
            'started_at' => '2026-08-01',
            'return_at' => '2027-06-30',
            'status' => Loan::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->user)->postJson(
            route('game.negotiate.transfer', [$this->game->id, $player->id]),
            ['action' => 'offer', 'bid' => 99_999_999]
        )->assertStatus(422)
            ->assertJsonPath('message', __('transfers.player_on_loan_unavailable'));
    }

    public function test_budget_loan_amount_above_max_rejected(): void
    {
        $this->actingAs($this->user)->post(
            route('game.budget-loan', $this->game->id),
            ['amount' => 100_000_000]
        )->assertSessionHasErrors('amount');
    }

    public function test_free_agent_wage_above_max_rejected(): void
    {
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->sellerTeam)->create([
            'team_id' => null,
            'contract_until' => null,
        ]);

        $this->actingAs($this->user)->postJson(
            route('game.negotiate.free-agent', [$this->game->id, $player->id]),
            ['action' => 'offer_terms', 'wage' => 100_000_000, 'years' => 3]
        )->assertStatus(422)->assertJsonValidationErrors('wage');
    }

    public function test_renewal_wage_above_max_rejected(): void
    {
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->create();

        $this->actingAs($this->user)->postJson(
            route('game.negotiate.renewal', [$this->game->id, $player->id]),
            ['action' => 'offer', 'wage' => 100_000_000, 'years' => 3]
        )->assertStatus(422)->assertJsonValidationErrors('wage');
    }
}
