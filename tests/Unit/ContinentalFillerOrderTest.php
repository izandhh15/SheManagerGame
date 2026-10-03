<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Season\Processors\UefaQualificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: UefaQualificationProcessor::fillRemainingContinentalSlots()
 * picked fillers with array_slice() over a pool query without ORDER BY —
 * physical row order decided who qualified. The pool is now ordered
 * deterministically: best domestic-league finish first, team id tiebreak.
 */
class ContinentalFillerOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_pool_orders_by_domestic_finish_then_team_id(): void
    {
        Competition::factory()->league()->create(['id' => 'ENG1']);
        Competition::factory()->league()->create(['id' => 'FRA1']);
        $game = Game::factory()->create([
            'competition_id' => 'ENG1',
            'season' => '2025',
            'current_date' => '2025-06-01',
        ]);

        $champion = Team::factory()->create(['name' => 'Champion FC']);
        $third = Team::factory()->create(['name' => 'Third FC']);
        $unranked = Team::factory()->create(['name' => 'Unranked FC']);
        $frenchSecond = Team::factory()->create(['name' => 'French Second']);

        $this->place($game, 'ENG1', $third, 3);
        $this->place($game, 'ENG1', $champion, 1);
        $this->place($game, 'FRA1', $frenchSecond, 2);

        $processor = app(UefaQualificationProcessor::class);
        $method = new \ReflectionMethod($processor, 'orderPoolByDomesticFinish');
        $method->setAccessible(true);

        // Deliberately unordered input: insertion order must not matter.
        $ordered = $method->invoke($processor, $game, [
            $unranked->id => 'ENG1',
            $third->id => 'ENG1',
            $frenchSecond->id => 'FRA1',
            $champion->id => 'ENG1',
        ]);

        $this->assertSame(
            [$champion->id, $frenchSecond->id, $third->id, $unranked->id],
            $ordered,
            'best domestic finish first; unranked teams last'
        );
    }

    public function test_tie_on_position_breaks_by_team_id(): void
    {
        Competition::factory()->league()->create(['id' => 'ENG1']);
        Competition::factory()->league()->create(['id' => 'FRA1']);
        $game = Game::factory()->create([
            'competition_id' => 'ENG1',
            'season' => '2025',
            'current_date' => '2025-06-01',
        ]);

        $teamA = Team::factory()->create(['name' => 'Team A']);
        $teamB = Team::factory()->create(['name' => 'Team B']);

        $this->place($game, 'ENG1', $teamA, 2);
        $this->place($game, 'FRA1', $teamB, 2);

        $processor = app(UefaQualificationProcessor::class);
        $method = new \ReflectionMethod($processor, 'orderPoolByDomesticFinish');
        $method->setAccessible(true);

        $expected = [$teamA->id, $teamB->id];
        sort($expected);

        // Pass in reverse-sorted order twice: the result must be identical.
        $first = $method->invoke($processor, $game, [$teamB->id => 'FRA1', $teamA->id => 'ENG1']);
        $second = $method->invoke($processor, $game, [$teamA->id => 'ENG1', $teamB->id => 'FRA1']);

        $this->assertSame($expected, $first);
        $this->assertSame($expected, $second);
    }

    private function place(Game $game, string $competitionId, Team $team, int $position): void
    {
        GameStanding::create([
            'game_id' => $game->id,
            'competition_id' => $competitionId,
            'team_id' => $team->id,
            'position' => $position,
            'played' => 38,
            'points' => 90 - $position,
        ]);
    }
}
