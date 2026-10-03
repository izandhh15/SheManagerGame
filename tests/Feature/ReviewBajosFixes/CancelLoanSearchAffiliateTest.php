<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA validación: CancelLoanSearch solo filtraba por team_id — las
 * búsquedas de cesión del filial no se podían cancelar (404). Ahora
 * usa $game->userTeamIds().
 */
class CancelLoanSearchAffiliateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Game $game;
    private Team $reserveTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $firstTeam = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $this->reserveTeam = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $firstTeam->id,
            'reserve_team_id' => $this->reserveTeam->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    private function listedPlayer(Team $team): GamePlayer
    {
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($team)->create();
        TransferListing::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'team_id' => $team->id,
            'status' => TransferListing::STATUS_LOAN_SEARCH,
            'listed_at' => $this->game->current_date,
        ]);

        return $player;
    }

    public function test_can_cancel_reserve_team_loan_search(): void
    {
        $player = $this->listedPlayer($this->reserveTeam);

        $response = $this->actingAs($this->user)->post(
            route('game.loans.cancel', [$this->game->id, $player->id])
        );

        $response->assertRedirect(route('game.transfers.outgoing', $this->game->id));
        $this->assertDatabaseMissing('transfer_listings', [
            'game_player_id' => $player->id,
            'status' => TransferListing::STATUS_LOAN_SEARCH,
        ]);
    }

    public function test_can_still_cancel_first_team_loan_search(): void
    {
        $player = $this->listedPlayer(Team::find($this->game->team_id));

        $response = $this->actingAs($this->user)->post(
            route('game.loans.cancel', [$this->game->id, $player->id])
        );

        $response->assertRedirect(route('game.transfers.outgoing', $this->game->id));
    }
}
