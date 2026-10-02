<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function gameWithMatches(User $user): Game
    {
        $team = Team::factory()->create(['name' => 'Player Team']);
        $opp1 = Team::factory()->create(['name' => 'Rival Uno']);
        $opp2 = Team::factory()->create(['name' => 'Rival Dos']);
        $oppOct = Team::factory()->create(['name' => 'Rival Octubre']);
        $comp = Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'Liga F']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $comp->id,
            'season' => '2026',
            'current_date' => '2026-09-10',
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
        ]);

        GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => $comp->id,
            'home_team_id' => $team->id,
            'away_team_id' => $opp1->id,
            'scheduled_date' => '2026-09-14 18:00:00',
            'played' => false,
        ]);
        GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => $comp->id,
            'home_team_id' => $opp2->id,
            'away_team_id' => $team->id,
            'scheduled_date' => '2026-09-21 20:00:00',
            'played' => false,
        ]);
        // A match in another month must not leak into September.
        GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => $comp->id,
            'home_team_id' => $team->id,
            'away_team_id' => $oppOct->id,
            'scheduled_date' => '2026-10-05 18:00:00',
            'played' => false,
        ]);

        return $game;
    }

    public function test_month_view_shows_each_match_on_its_day(): void
    {
        $user = User::factory()->create();
        $game = $this->gameWithMatches($user);

        $response = $this->actingAs($user)->get(route('game.calendar.month', ['gameId' => $game->id, 'ym' => '2026-09']));

        $response->assertOk();
        $html = $response->getContent();
        // Both September matches are rendered (Rival Uno only plays in September)…
        $this->assertStringContainsString('Rival Uno', $html);
        // …and the October-only fixture is not.
        $this->assertStringNotContainsString('Rival Octubre', $html);
        // Navigation links to adjacent months exist.
        $this->assertStringContainsString('ym=2026-08', $html);
        $this->assertStringContainsString('ym=2026-10', $html);
    }

    public function test_month_view_defaults_to_the_game_current_month(): void
    {
        $user = User::factory()->create();
        $game = $this->gameWithMatches($user);

        $response = $this->actingAs($user)->get(route('game.calendar.month', $game->id));

        $response->assertOk();
        $this->assertStringContainsString('Rival Uno', $response->getContent());
    }
}
