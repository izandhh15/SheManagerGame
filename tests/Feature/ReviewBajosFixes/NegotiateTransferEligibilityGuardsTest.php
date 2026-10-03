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
 * BAJA validación: los guards de handleStart en NegotiateTransfer
 * (jugadora propia / cedida) no se re-ejecutaban en offer,
 * accept_counter ni start_terms. Defensa en profundidad.
 */
class NegotiateTransferEligibilityGuardsTest extends TestCase
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

    private function postAction(GamePlayer $player, string $action, array $extra = [])
    {
        return $this->actingAs($this->user)->postJson(
            route('game.negotiate.transfer', [$this->game->id, $player->id]),
            array_merge(['action' => $action], $extra)
        );
    }

    public function test_offer_on_own_player_rejected(): void
    {
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->create();

        foreach (['offer', 'accept_counter', 'start_terms'] as $action) {
            $payload = $action === 'offer' ? ['bid' => 1000000] : [];
            $this->postAction($player, $action, $payload)->assertStatus(422)
                ->assertJsonPath('message', __('transfers.cannot_target_own_player'));
        }
    }

    public function test_offer_on_loaned_player_rejected(): void
    {
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

        $this->postAction($player, 'offer', ['bid' => 1000000])->assertStatus(422)
            ->assertJsonPath('message', __('transfers.player_on_loan_unavailable'));
        $this->postAction($player, 'start_terms')->assertStatus(422)
            ->assertJsonPath('message', __('transfers.player_on_loan_unavailable'));
    }
}
