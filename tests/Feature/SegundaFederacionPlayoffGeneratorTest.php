<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Playoffs\SegundaFederacionPlayoffGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: SegundaFederacionPlayoffGenerator::orderedTeamsFromSimulation
 * used to resolve a non-existent SeasonSimulationService
 * (App\Modules\Competition\Services\...) and passed team IDs where the
 * service expects a Competition — crashing playoff generation at season end
 * for every ESP3 group game. The fallback must lazily simulate the group
 * via the real service (App\Modules\Finance\Services\SeasonSimulationService).
 */
class SegundaFederacionPlayoffGeneratorTest extends TestCase
{
    use RefreshDatabase;

    /** Local pgsql test DB (see phpunit.xml); independent of TestCase's override. */
    protected $connectionsToTransact = ['pgsql'];

    public function test_semifinal_matchups_fall_back_to_simulated_standings(): void
    {
        foreach (['ESP3A', 'ESP3B', 'ESP3C'] as $id) {
            Competition::factory()->league()->create(['id' => $id, 'country' => 'ES', 'tier' => 3]);
        }
        Competition::factory()->create(['id' => 'ESP3PO', 'country' => 'ES', 'tier' => 3]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP3A',
            'country' => 'ES',
            'season' => '2026',
        ]);

        // Four teams per group, no real standings: forces the simulation fallback.
        foreach (['ESP3A', 'ESP3B', 'ESP3C'] as $groupId) {
            for ($i = 0; $i < 4; $i++) {
                CompetitionEntry::create([
                    'game_id' => $game->id,
                    'competition_id' => $groupId,
                    'team_id' => Team::factory()->create(['country' => 'ES'])->id,
                ]);
            }
        }

        $matchups = (new SegundaFederacionPlayoffGenerator())->generateMatchups($game, 1);

        // Seed 1 vs seed 4, seed 2 vs seed 3 (two legs each).
        $this->assertCount(2, $matchups);
        $this->assertCount(3, $matchups[0]);
        $this->assertCount(3, $matchups[1]);

        // The four seeds are distinct teams.
        $seeds = [$matchups[0][0], $matchups[0][1], $matchups[1][0], $matchups[1][1]];
        $this->assertCount(4, array_unique($seeds));
    }
}
