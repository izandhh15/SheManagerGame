<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Services\SyntheticLeagueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lane-invariant tests for SyntheticLeagueResolver: real GameStanding rows
 * and SimulatedSeason rows must never coexist for the same
 * (game, competition, season) lane. The resolver refuses to write
 * standings once a simulated lane is committed, and never writes a
 * simulated lane itself.
 */
class SyntheticLeagueLaneInvariantTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create([
            'id' => 'ESP2', 'tier' => 2, 'handler_type' => 'league_with_playoff',
        ]);
        Competition::factory()->league()->create([
            'id' => 'ESP3A', 'tier' => 3, 'handler_type' => 'league_with_playoff',
        ]);
        Competition::factory()->league()->create([
            'id' => 'ESP3B', 'tier' => 3, 'handler_type' => 'league_with_playoff',
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP3A',
            'season' => '2025',
        ]);
    }

    // ──────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────

    private function createSimulatedSeason(string $competitionId, array $teams): void
    {
        $teamIds = [];
        foreach ($teams as $team) {
            $teamIds[] = $team->id;

            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => $competitionId,
            'results' => $teamIds,
        ]);
    }

    private function createSimulatedTeams(int $count): array
    {
        $teams = [];
        for ($i = 0; $i < $count; $i++) {
            $teams[] = Team::factory()->create();
        }
        return $teams;
    }

    // ──────────────────────────────────────────────────
    // Lane invariant: simulated and standings cannot coexist
    // ──────────────────────────────────────────────────

    public function test_resolver_refuses_to_write_standings_after_simulated_lane_committed(): void
    {
        $this->game->update(['competition_id' => 'ESP2']);

        $simulatedA = $this->createSimulatedTeams(20);
        $simulatedB = $this->createSimulatedTeams(20);
        $this->createSimulatedSeason('ESP3A', $simulatedA);
        $this->createSimulatedSeason('ESP3B', $simulatedB);

        $resolver = app(SyntheticLeagueResolver::class);
        $esp3a = Competition::find('ESP3A');

        $resolver->catchUp($this->game, $esp3a, $this->game->current_date?->copy()->addYear());

        $this->assertSame(0, GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3A')
            ->count(), 'catchUp must not write game_standings when SimulatedSeason already exists.');
        $this->assertSame(0, GameMatch::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3A')
            ->count(), 'catchUp must not write game_matches when SimulatedSeason already exists.');
    }

    public function test_resolver_initializes_when_no_simulated_season_exists(): void
    {
        $this->game->update(['competition_id' => 'ESP2']);

        $teams = $this->createSimulatedTeams(20);
        foreach ($teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP3A',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        $resolver = app(SyntheticLeagueResolver::class);
        $esp3a = Competition::find('ESP3A');

        $resolver->ensureInitialized($this->game, $esp3a);

        $this->assertGreaterThan(0, GameMatch::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3A')
            ->count(), 'ensureInitialized should create fixtures when no lane is locked yet.');
        $this->assertGreaterThan(0, GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3A')
            ->count(), 'ensureInitialized should seed standings when no lane is locked yet.');
        $this->assertSame(0, SimulatedSeason::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3A')
            ->where('season', $this->game->season)
            ->count(), 'No SimulatedSeason should be written by the resolver itself.');
    }
}
