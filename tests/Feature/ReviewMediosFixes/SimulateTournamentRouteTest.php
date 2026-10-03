<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TRIAGE-B G14: SimulateTournament was a GET route with mutating effects
 * (CSRF-able via prefetch/<img>) that ran up to 500 advance() calls in one
 * request — the same serverless-timeout class as the 0.3.67 season
 * transition fix. It is now a chunked POST JSON endpoint.
 */
class SimulateTournamentRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_post_only_and_keeps_its_name(): void
    {
        $route = Route::getRoutes()->getByName('game.simulate-tournament');

        $this->assertNotNull($route, 'route game.simulate-tournament must exist');
        $this->assertContains('POST', $route->methods);
        $this->assertNotContains('GET', $route->methods);
    }

    public function test_get_returns_405(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['user_id' => $user->id, 'game_mode' => 'career']);

        $this->actingAs($user)
            ->get(route('game.simulate-tournament', $game->id))
            ->assertStatus(405);
    }

    public function test_post_to_non_tournament_game_returns_done_json(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['user_id' => $user->id, 'game_mode' => 'career']);

        $response = $this->actingAs($user)
            ->post(route('game.simulate-tournament', $game->id));

        $response->assertOk();
        $response->assertJson(['done' => true]);
        $this->assertStringContainsString(
            "/game/{$game->id}",
            $response->json('redirect'),
            'non-tournament games must redirect back to the game view'
        );
    }

    public function test_post_rejects_other_users_games(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $game = Game::factory()->create(['user_id' => $owner->id, 'game_mode' => Game::MODE_TOURNAMENT]);

        $this->actingAs($intruder)
            ->post(route('game.simulate-tournament', $game->id))
            ->assertForbidden();
    }
}
