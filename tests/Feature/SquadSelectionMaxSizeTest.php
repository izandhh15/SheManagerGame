<?php

namespace Tests\Feature;

use App\Http\Actions\SaveSquadSelection;
use App\Models\Game;
use App\Models\GamePlayerTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: the tournament squad-size "26" lived as a magic number in
 * three places (ShowSquadSelection auto-select threshold, the
 * squad-selection Blade counters, the Alpine maxPlayers). It is now the
 * shared SaveSquadSelection::MAX_SQUAD_SIZE constant.
 */
class SquadSelectionMaxSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_max_squad_size_constant_is_26(): void
    {
        $this->assertSame(26, SaveSquadSelection::MAX_SQUAD_SIZE);
    }

    public function test_roster_at_or_below_max_is_auto_selected(): void
    {
        [$user, $game] = $this->tournamentGame(candidateCount: 20);

        $response = $this->actingAs($user)->get("/game/{$game->id}/squad-selection");

        $response->assertRedirect(route('show-game', $game->id));
        $this->assertSame(20, \App\Models\GamePlayer::where('game_id', $game->id)->count());
    }

    public function test_roster_above_max_renders_picker_with_shared_counter(): void
    {
        [$user, $game] = $this->tournamentGame(candidateCount: 27);

        $response = $this->actingAs($user)->get("/game/{$game->id}/squad-selection");

        $response->assertOk();
        // The Blade counters render from the shared constant, not a literal.
        $response->assertSee('/ 26', escape: false);
        $response->assertSee('maxPlayers: 26', escape: false);
    }

    /**
     * @return array{User, Game}
     */
    private function tournamentGame(int $candidateCount): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'name' => 'Tournament FC',
            'type' => 'club',
            'transfermarkt_id' => (string) random_int(100000, 999999),
        ]);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'game_mode' => Game::MODE_TOURNAMENT,
            'season' => '2026',
            'current_date' => '2026-07-01',
            'needs_new_season_setup' => true,
            'setup_completed_at' => now(),
        ]);

        $positions = ['Goalkeeper', 'Centre-Back', 'Central Midfield', 'Centre-Forward'];
        for ($i = 0; $i < $candidateCount; $i++) {
            GamePlayerTemplate::create([
                'season' => '2026',
                'player_id' => \Illuminate\Support\Str::uuid()->toString(),
                'team_id' => $team->id,
                'transfermarkt_id' => '900'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'name' => "Candidate {$i}",
                'position' => $positions[$i % 4],
                'overall_score' => 80,
            ]);
        }

        return [$user, $game];
    }
}
