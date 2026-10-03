<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * BAJA validación: ComputeSlotAssignments aceptaba player_ids sin
 * límite — vector de DoS barato contra el whereIn. Ahora max:11.
 */
class ComputeSlotAssignmentsMaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_more_than_11_player_ids(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $ids = collect(range(1, 12))->map(fn () => (string) Str::uuid())->all();

        $this->actingAs($user)->postJson(
            route('game.lineup.computeSlots', $game->id),
            ['formation' => '4-4-2', 'player_ids' => $ids]
        )->assertStatus(422)->assertJsonValidationErrors('player_ids');
    }

    public function test_accepts_11_player_ids(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $ids = collect(range(1, 11))->map(fn () => (string) Str::uuid())->all();

        $this->actingAs($user)->postJson(
            route('game.lineup.computeSlots', $game->id),
            ['formation' => '4-4-2', 'player_ids' => $ids]
        )->assertOk();
    }
}
