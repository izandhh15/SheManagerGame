<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Modules\Competition\Promotions\StandingsReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: StandingsReader::reconcileWithEntries() paired stale→missing
 * team ids with array_combine() over raw list order — but the missing side
 * comes from an unordered CompetitionEntry::pluck(), so the mapping depended
 * on physical DB row order. Both sides are now sorted by team_id, making the
 * reconciliation deterministic for the same roster state.
 */
class StandingsReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_pairs_by_team_id_not_row_order(): void
    {
        // The same roster drift, built twice with the missing entries inserted
        // in opposite order. The old order-based pairing reconciled the two
        // builds differently; the team_id-sorted pairing must reconcile both
        // identically (each stale slot gets the missing team of the same
        // sorted rank, regardless of insertion order).
        Competition::factory()->league()->create(['id' => 'ESP1']);

        foreach ([true, false] as $reversed) {
            $build = $this->buildDriftedGame([$reversed]);
            $read = (new StandingsReader)->read($build['game'], 'ESP1');

            $staleIds = [$build['stale1']->id, $build['stale2']->id];
            sort($staleIds);
            $missingIds = [$build['missing1']->id, $build['missing2']->id];
            sort($missingIds);
            $map = array_combine($staleIds, $missingIds);
            $expected = [
                $build['teamA']->id,
                $map[$build['stale1']->id],
                $map[$build['stale2']->id],
                $build['teamB']->id,
            ];

            $this->assertSame($expected, array_column($read, 'teamId'), $reversed ? 'reversed insertion order' : 'natural insertion order');
            $this->assertSame([1, 2, 3, 4], array_column($read, 'position'));

            // The reconciliation is persisted so subsequent reads see it too.
            $this->assertSame($expected, $build['simulated']->refresh()->results);
        }
    }

    /**
     * Build a game whose SimulatedSeason.results drifted from the
     * CompetitionEntry roster (two stale teams in, two missing teams out).
     *
     * @return array{game: Game, teamA: Team, teamB: Team, stale1: Team, stale2: Team, missing1: Team, missing2: Team, simulated: SimulatedSeason}
     */
    private function buildDriftedGame(array $missingInsertionReversed): array
    {
        $teamA = Team::factory()->create(['name' => 'Team A']);
        $teamB = Team::factory()->create(['name' => 'Team B']);
        $stale1 = Team::factory()->create(['name' => 'Stale One']);
        $stale2 = Team::factory()->create(['name' => 'Stale Two']);
        $missing1 = Team::factory()->create(['name' => 'Missing One']);
        $missing2 = Team::factory()->create(['name' => 'Missing Two']);

        $game = Game::factory()->create([
            'team_id' => $teamA->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);

        $missingOrder = $missingInsertionReversed[0]
            ? [$missing2->id, $missing1->id]
            : [$missing1->id, $missing2->id];

        foreach ([$teamA->id, $teamB->id, ...$missingOrder] as $teamId) {
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => 'ESP1',
                'team_id' => $teamId,
            ]);
        }

        $simulated = SimulatedSeason::create([
            'game_id' => $game->id,
            'season' => '2025',
            'competition_id' => 'ESP1',
            'results' => [$teamA->id, $stale1->id, $stale2->id, $teamB->id],
        ]);

        return compact('game', 'teamA', 'teamB', 'stale1', 'stale2', 'missing1', 'missing2', 'simulated');
    }

    public function test_no_drift_leaves_results_untouched(): void
    {
        $teamA = Team::factory()->create(['name' => 'Team A']);
        $teamB = Team::factory()->create(['name' => 'Team B']);

        Competition::factory()->league()->create(['id' => 'ESP1']);
        $game = Game::factory()->create([
            'team_id' => $teamA->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);

        foreach ([$teamA->id, $teamB->id] as $teamId) {
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => 'ESP1',
                'team_id' => $teamId,
            ]);
        }

        SimulatedSeason::create([
            'game_id' => $game->id,
            'season' => '2025',
            'competition_id' => 'ESP1',
            'results' => [$teamA->id, $teamB->id],
        ]);

        $read = (new StandingsReader)->read($game, 'ESP1');

        $this->assertSame([$teamA->id, $teamB->id], array_column($read, 'teamId'));
    }
}
