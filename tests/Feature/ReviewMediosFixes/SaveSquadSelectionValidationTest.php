<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TRIAGE-B G14: SaveSquadSelection validated only `required|array|max:26` —
 * duplicate IDs created duplicate GamePlayer rows and 2–3-player squads
 * left a broken tournament roster. It also called file_get_contents()
 * without file_exists(), 500ing when the team's JSON was missing.
 */
class SaveSquadSelectionValidationTest extends TestCase
{
    use RefreshDatabase;

    private function tournamentGame(User $user, ?int $transfermarktId = 999999): Game
    {
        $team = Team::factory()->create(['transfermarkt_id' => $transfermarktId]);

        return Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'game_mode' => Game::MODE_TOURNAMENT,
            'needs_new_season_setup' => true,
        ]);
    }

    public function test_duplicate_player_ids_are_rejected(): void
    {
        $user = User::factory()->create();
        $game = $this->tournamentGame($user);

        $response = $this->actingAs($user)->post(
            route('game.squad-selection.save', $game->id),
            ['player_ids' => array_fill(0, 20, 'tm-dup-1')]
        );

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('game_players', ['game_id' => $game->id]);
    }

    public function test_squad_below_minimum_is_rejected(): void
    {
        $user = User::factory()->create();
        $game = $this->tournamentGame($user);

        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $ids[] = "tm-min-{$i}";
        }

        $response = $this->actingAs($user)->post(
            route('game.squad-selection.save', $game->id),
            ['player_ids' => $ids]
        );

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('game_players', ['game_id' => $game->id]);
    }

    public function test_squad_above_maximum_is_rejected(): void
    {
        $user = User::factory()->create();
        $game = $this->tournamentGame($user);

        $ids = [];
        for ($i = 0; $i < 27; $i++) {
            $ids[] = "tm-max-{$i}";
        }

        $response = $this->actingAs($user)->post(
            route('game.squad-selection.save', $game->id),
            ['player_ids' => $ids]
        );

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('game_players', ['game_id' => $game->id]);
    }

    public function test_missing_team_json_returns_controlled_error_not_500(): void
    {
        $user = User::factory()->create();
        // transfermarkt_id with no JSON file in data/2025/WC2026/teams/
        $game = $this->tournamentGame($user, 42424242);

        $ids = [];
        for ($i = 0; $i < 20; $i++) {
            $ids[] = "tm-ok-{$i}";
        }

        $response = $this->actingAs($user)->post(
            route('game.squad-selection.save', $game->id),
            ['player_ids' => $ids]
        );

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('game_players', ['game_id' => $game->id]);
    }
}
