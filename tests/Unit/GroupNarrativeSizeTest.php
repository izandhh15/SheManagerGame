<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Match\Services\MatchNarrativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: MatchNarrativeService::tournamentGroupCandidates() treated
 * $played === 2 as "final group match" (and $position === 4 as "last place"),
 * hardcoding 4-team groups / 3 matchdays. Both are now derived from the
 * actual group size.
 */
class GroupNarrativeSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_matchday_is_group_size_minus_one(): void
    {
        $service = app(MatchNarrativeService::class);

        // 5-team group: the final group game is matchday 4, not 3.
        $standing = $this->standing(played: 4, position: 2, points: 6);

        $candidates = $this->groupCandidates($service, $standing, 5);

        $this->assertSame('wc_group_qualified', $candidates[0]['key']);
    }

    public function test_matchday_two_is_not_final_in_a_five_team_group(): void
    {
        $service = app(MatchNarrativeService::class);

        // Same played count in a 5-team group is just matchday 2 of 4.
        $standing = $this->standing(played: 2, position: 1, points: 6);

        $candidates = $this->groupCandidates($service, $standing, 5);

        // Falls through to the "after matchday 1" branch, not the final-day drama.
        $this->assertSame('wc_group_top', $candidates[0]['key']);
    }

    public function test_last_place_uses_group_size(): void
    {
        $service = app(MatchNarrativeService::class);

        // 3-team group, bottom with 2 points: not must-win, not on the brink.
        $standing = $this->standing(played: 2, position: 3, points: 2);

        $candidates = $this->groupCandidates($service, $standing, 3);

        $this->assertSame('wc_group_gd', $candidates[0]['key']);
    }

    public function test_group_size_is_counted_from_standings(): void
    {
        // WOLYMP is seeded by the migrations; no need to create it.
        $game = Game::factory()->create([
            'competition_id' => 'WOLYMP',
            'season' => '2028',
            'current_date' => '2028-07-01',
        ]);

        $standing = null;
        for ($i = 0; $i < 5; $i++) {
            $team = Team::factory()->create();
            $row = GameStanding::create([
                'game_id' => $game->id,
                'competition_id' => 'WOLYMP',
                'group_label' => 'B',
                'team_id' => $team->id,
                'position' => $i + 1,
                'played' => 0,
            ]);
            $standing ??= $row;
        }

        $method = new \ReflectionMethod(MatchNarrativeService::class, 'groupSize');
        $method->setAccessible(true);

        $this->assertSame(5, $method->invoke(app(MatchNarrativeService::class), $game, $standing));
    }

    public function test_group_size_falls_back_to_four_without_a_group(): void
    {
        $method = new \ReflectionMethod(MatchNarrativeService::class, 'groupSize');
        $method->setAccessible(true);

        $game = Game::factory()->create(['season' => '2028', 'current_date' => '2028-07-01']);

        $this->assertSame(4, $method->invoke(app(MatchNarrativeService::class), $game, null));
    }

    private function standing(int $played, int $position, int $points): GameStanding
    {
        $standing = new GameStanding;
        $standing->played = $played;
        $standing->position = $position;
        $standing->points = $points;
        $standing->group_label = 'A';

        return $standing;
    }

    private function groupCandidates(MatchNarrativeService $service, GameStanding $standing, int $groupSize): array
    {
        $method = new \ReflectionMethod($service, 'tournamentGroupCandidates');
        $method->setAccessible(true);

        return $method->invoke($service, $standing, $groupSize);
    }
}
