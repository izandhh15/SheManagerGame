<?php

namespace Tests\Feature;

use App\Models\CareerHistory;
use App\Models\Competition;
use App\Models\Game;
use App\Models\ManagerStats;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\GameDeletionService;
use App\Modules\Social\Services\CareerHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Career history: deleting a game snapshots team + stats so the career
 * stays visible to friends even after the save rows are gone.
 */
class CareerHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Local pgsql test DB (see phpunit.xml); independent of TestCase's override. */
    protected $connectionsToTransact = ['pgsql'];

    private function gameWithStats(): Game
    {
        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Valencia CF', 'type' => 'club', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
        ]);

        ManagerStats::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'team_id' => $team->id,
            'matches_played' => 30,
            'matches_won' => 18,
            'matches_drawn' => 6,
            'matches_lost' => 6,
            'trophies_count' => 2,
            'seasons_completed' => 1,
        ]);

        return $game->fresh();
    }

    public function test_snapshot_on_delete_captures_team_and_stats(): void
    {
        $game = $this->gameWithStats();

        $snapshot = app(CareerHistoryService::class)->snapshotOnDelete($game);

        $this->assertNotNull($snapshot);
        $this->assertEquals($game->user_id, $snapshot->user_id);
        $this->assertEquals($game->id, $snapshot->game_id);
        $this->assertEquals('Valencia CF', $snapshot->team_name);
        $this->assertEquals('club', $snapshot->team_type);
        $this->assertEquals('2026', $snapshot->season);
        $this->assertEquals(30, $snapshot->stats['matches']);
        $this->assertEquals(18, $snapshot->stats['wins']);
        $this->assertEquals(2, $snapshot->stats['trophies']);
        $this->assertNotNull($snapshot->deleted_at);
    }

    public function test_snapshot_is_idempotent(): void
    {
        $game = $this->gameWithStats();
        $svc = app(CareerHistoryService::class);

        $svc->snapshotOnDelete($game);
        $svc->snapshotOnDelete($game);

        $this->assertEquals(1, CareerHistory::where('game_id', $game->id)->count());
    }

    public function test_snapshot_without_stats_still_works(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Spain', 'type' => 'national']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
        ]);

        $snapshot = app(CareerHistoryService::class)->snapshotOnDelete($game->fresh());

        // Team->name is localized (Spain -> España in es); the snapshot keeps
        // the display name, consistent with the rest of the UI.
        $this->assertEquals($team->name, $snapshot->team_name);
        $this->assertEquals('national', $snapshot->team_type);
        $this->assertEquals(0, $snapshot->stats['matches']);
    }

    public function test_game_deletion_service_snapshots_before_deleting(): void
    {
        $game = $this->gameWithStats();
        $gameId = $game->id;
        $userId = $game->user_id;

        app(GameDeletionService::class)->delete($game->fresh());

        // Snapshot survives the deletion (in testing the queue is sync, so
        // the async job already wiped the game rows).
        $this->assertDatabaseHas('career_history', [
            'user_id' => $userId,
            'game_id' => $gameId,
            'team_name' => 'Valencia CF',
        ]);
        $this->assertDatabaseMissing('games', ['id' => $gameId]);
    }

    public function test_history_for_returns_most_recent_first(): void
    {
        $game = $this->gameWithStats();
        $svc = app(CareerHistoryService::class);
        $svc->snapshotOnDelete($game);

        $history = $svc->historyFor(User::find($game->user_id));

        $this->assertCount(1, $history);
        $this->assertEquals('Valencia CF', $history->first()->team_name);
    }
}
