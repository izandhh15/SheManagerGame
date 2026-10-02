<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentStadiumRequestTest extends TestCase
{
    use RefreshDatabase;

    private function filialGame(): array
    {
        $user = User::factory()->create();
        $parent = Team::factory()->create([
            'name' => 'Valencia CF',
            'type' => 'club',
            'stadium_name' => 'Mestalla',
            'stadium_seats' => 50000,
        ]);
        $filial = Team::factory()->create([
            'name' => 'Valencia CF B',
            'type' => 'club',
            'parent_team_id' => $parent->id,
            'stadium_name' => 'Mini Estadi',
            'stadium_seats' => 5000,
        ]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $filial->id,
        ]);

        return [$game, $user, $parent, $filial];
    }

    public function test_filial_can_request_parent_stadium(): void
    {
        [$game, $user, $parent, $filial] = $this->filialGame();

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $filial->id,
            'played' => false,
            'scheduled_date' => '2026-10-15 20:00:00',
        ]);

        $response = $this->actingAs($user)->post(
            "/game/{$game->id}/club/stadium/parent-stadium",
            ['match_id' => $match->id],
        );

        $response->assertRedirect();
        $this->assertSame('Mestalla', $match->fresh()->neutral_venue_name);
        $this->assertSame(50000, $match->fresh()->neutral_venue_capacity);
    }

    public function test_rejected_when_parent_busy_same_day(): void
    {
        [$game, $user, $parent, $filial] = $this->filialGame();

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $filial->id,
            'played' => false,
            'scheduled_date' => '2026-10-15 20:00:00',
        ]);

        // Parent plays at home the same day.
        GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $parent->id,
            'played' => false,
            'scheduled_date' => '2026-10-15 18:00:00',
        ]);

        $response = $this->actingAs($user)->post(
            "/game/{$game->id}/club/stadium/parent-stadium",
            ['match_id' => $match->id],
        );

        $response->assertRedirect();
        $this->assertNull($match->fresh()->neutral_venue_name);
    }

    public function test_non_filial_cannot_request(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'stadium_name' => 'Campo']);
        $game = Game::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $team->id,
            'played' => false,
            'scheduled_date' => '2026-10-15 20:00:00',
        ]);

        $response = $this->actingAs($user)->post(
            "/game/{$game->id}/club/stadium/parent-stadium",
            ['match_id' => $match->id],
        );

        $response->assertRedirect();
        $this->assertNull($match->fresh()->neutral_venue_name);
    }
}
