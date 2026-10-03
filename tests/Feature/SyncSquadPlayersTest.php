<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\NationalSquadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression for the 03-10-2026 500 on confirming a convocatoria:
 * syncSquadPlayers() used a nowdoc (<<<'SQL') so $placeholders was never
 * interpolated, and Postgres threw "Invalid parameter number".
 */
class SyncSquadPlayersTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_squad_players_inserts_from_templates(): void
    {
        $user = User::factory()->create();
        $spain = Team::factory()->create(['name' => 'Spain', 'type' => 'national', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $spain->id,
            'season' => '2026',
            'current_date' => '2026-11-18',
        ]);

        $playerIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $pid = \Illuminate\Support\Str::uuid()->toString();
            $playerIds[] = $pid;
            DB::table('game_player_templates')->insert([
                'season' => '2026',
                'team_id' => $spain->id,
                'player_id' => $pid,
                'name' => "Test Player $i",
                'position' => 'Midfielder',
                'overall_score' => 80,
            ]);
        }

        // Must not throw (was: QueryException HY093).
        NationalSquadService::syncSquadPlayers($game, $playerIds);

        $this->assertSame(3, GamePlayer::where('game_id', $game->id)->count());
        foreach ($playerIds as $pid) {
            $this->assertTrue(
                GamePlayer::where('game_id', $game->id)->where('player_id', $pid)->exists(),
                "player $pid not synced"
            );
        }
    }
}
