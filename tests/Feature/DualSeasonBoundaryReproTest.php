<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Repro for: "no se puede jugar siguiente temporada" (dual Valencia + Spain).
 * Scenario: club season complete, Spain mid-competition. User POSTs
 * start-new-season on the club game. What happens?
 */
class DualSeasonBoundaryReproTest extends TestCase
{
    use RefreshDatabase;

    private function makeDualPair(): array
    {
        $user = User::factory()->create();

        Competition::factory()->create(['id' => 'ESP1']);
        Competition::factory()->create(['id' => 'WQUEFA']);

        $clubTeam = Team::factory()->create(['name' => 'Valencia CF', 'type' => 'club']);
        $clubGame = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $clubTeam->id,
            'game_mode' => Game::MODE_CAREER,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => '2027-05-30',
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
        ]);

        $ntTeam = Team::factory()->create(['name' => 'Spain', 'type' => 'national']);
        $ntGame = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $ntTeam->id,
            'game_mode' => Game::MODE_TOURNAMENT,
            'competition_id' => 'WQUEFA',
            'season' => '2027',
            'current_date' => '2027-05-30',
            'linked_game_id' => $clubGame->id,
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
        ]);

        // Spain has a pending match (mid-WQUEFA)
        GameMatch::factory()->create([
            'game_id' => $ntGame->id,
            'home_team_id' => $ntTeam->id,
            'away_team_id' => Team::factory()->create(['type' => 'national'])->id,
            'scheduled_date' => '2027-06-02',
            'played' => false,
            'competition_id' => 'WQUEFA',
        ]);

        return [$user, $clubGame, $ntGame];
    }

    public function test_club_can_start_new_season_while_spain_has_pending_matches(): void
    {
        [$user, $clubGame, $ntGame] = $this->makeDualPair();

        // Club: all matches played (season complete) — no matches at all here.
        $this->assertSame(0, $clubGame->matches()->where('played', false)->count());

        $response = $this->actingAs($user)->post(route('game.start-new-season', $clubGame->id));

        $clubGame->refresh();
        $ntGame->refresh();

        fwrite(STDERR, "\n[repro] response status: {$response->getStatusCode()}\n");
        fwrite(STDERR, '[repro] redirect: ' . $response->headers->get('Location') . "\n");
        fwrite(STDERR, '[repro] session errors: ' . json_encode(session()->get('errors')?->all() ?? []) . "\n");
        fwrite(STDERR, "[repro] club season_transitioning_at: {$clubGame->season_transitioning_at}\n");
        fwrite(STDERR, "[repro] club season: {$clubGame->season}\n");
        fwrite(STDERR, "[repro] spain competition: {$ntGame->competition_id}, season: {$ntGame->season}\n");
        fwrite(STDERR, '[repro] spain linked_game_id: ' . var_export($ntGame->linked_game_id, true) . "\n");

        // What does the user see when visiting the club game now?
        $show = $this->actingAs($user)->get(route('show-game', $clubGame->id));
        fwrite(STDERR, '[repro] show-game status: ' . $show->getStatusCode() . "\n");
        if ($show->isRedirect()) {
            fwrite(STDERR, '[repro] show-game redirect: ' . $show->headers->get('Location') . "\n");
        }

        $this->assertTrue(true);
    }
}
