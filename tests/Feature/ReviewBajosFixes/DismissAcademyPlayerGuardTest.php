<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA validación: DismissAcademyPlayer no filtraba is_on_loan — un POST
 * forjado expulsaba a una canterana cedida. Ahora exige
 * where('is_on_loan', false) como LoanAcademyPlayer.
 */
class DismissAcademyPlayerGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    private function makeAcademyPlayer(bool $onLoan): AcademyPlayer
    {
        return AcademyPlayer::create([
            'game_id' => $this->game->id,
            'team_id' => $this->game->team_id,
            'name' => 'Test Academy Player',
            'nationality' => ['Spain'],
            'date_of_birth' => '2008-01-01',
            'position' => 'Central Midfield',
            'overall_score' => 55,
            'potential' => 80,
            'potential_low' => 75,
            'potential_high' => 88,
            'appeared_at' => '2026-08-15',
            'is_on_loan' => $onLoan,
            'is_jewel' => false,
            'joined_season' => 2026,
            'initial_overall' => 55,
        ]);
    }

    public function test_cannot_dismiss_loaned_academy_player(): void
    {
        $player = $this->makeAcademyPlayer(true);

        $response = $this->actingAs($this->user)->post(
            route('game.academy.dismiss', [$this->game->id, $player->id])
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('academy_players', ['id' => $player->id]);
    }

    public function test_can_dismiss_regular_academy_player(): void
    {
        $player = $this->makeAcademyPlayer(false);

        $response = $this->actingAs($this->user)->post(
            route('game.academy.dismiss', [$this->game->id, $player->id])
        );

        $response->assertRedirect(route('game.squad.academy', $this->game->id));
        $this->assertDatabaseMissing('academy_players', ['id' => $player->id]);
    }
}
