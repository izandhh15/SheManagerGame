<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerDetailReproTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_detail_modal_for_reserve_player(): void
    {
        \App\Models\Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Valencia CF Femenino', 'country' => 'ES']);
        $reserve = Team::factory()->create(['name' => 'Valencia CF B', 'country' => 'ES']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'reserve_team_id' => $reserve->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-10-02',
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $reserve->id,
            'name' => 'Rocío Pardo',
            'overall_score' => 63,
            'position' => 'Goalkeeper',
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get("/game/{$game->id}/player/{$player->id}/detail");

        $response->assertStatus(200);
    }
}
