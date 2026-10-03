<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Lineup\Services\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: SubstitutionService::buildActiveLineup() resolved lineup ids
 * with a bare whereIn('id', …) — no game_id scope. Player UUIDs are globally
 * unique today so it works, but a stale lineup reference pointing at a
 * player row from another game would leak across. The lookup is now scoped
 * to the match's game.
 */
class BuildActiveLineupScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_lineup_ids_only_resolve_within_the_match_game(): void
    {
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $teamA = Team::factory()->create(['name' => 'Team A']);
        $teamB = Team::factory()->create(['name' => 'Team B']);

        $gameA = Game::factory()->create([
            'team_id' => $teamA->id, 'competition_id' => 'ESP1',
            'season' => '2025', 'current_date' => '2025-08-15',
        ]);
        $gameB = Game::factory()->create([
            'team_id' => $teamB->id, 'competition_id' => 'ESP1',
            'season' => '2025', 'current_date' => '2025-08-15',
        ]);

        $playerA = GamePlayer::factory()->forGame($gameA)->forTeam($teamA)
            ->create(['date_of_birth' => '1998-06-15']);
        // A player from ANOTHER game whose id ends up in the lineup
        // (stale reference after data drift).
        $foreignPlayer = GamePlayer::factory()->forGame($gameB)->forTeam($teamB)
            ->create(['date_of_birth' => '1999-03-20']);

        $match = GameMatch::factory()->forGame($gameA)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'home_lineup' => [$playerA->id, $foreignPlayer->id],
        ]);

        $lineup = app(SubstitutionService::class)->buildActiveLineup($match, $teamA->id, []);

        $this->assertSame([$playerA->id], $lineup->pluck('id')->all());
    }

    public function test_substitutions_still_apply_with_game_scope(): void
    {
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $teamA = Team::factory()->create(['name' => 'Team A']);
        $teamB = Team::factory()->create(['name' => 'Team B']);

        $game = Game::factory()->create([
            'team_id' => $teamA->id, 'competition_id' => 'ESP1',
            'season' => '2025', 'current_date' => '2025-08-15',
        ]);

        $mk = fn () => GamePlayer::factory()->forGame($game)->forTeam($teamA)
            ->create(['date_of_birth' => '1998-06-15']);
        $starter = $mk();
        $subIn = $mk();

        $match = GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'home_lineup' => [$starter->id],
        ]);

        $lineup = app(SubstitutionService::class)->buildActiveLineup(
            $match,
            $teamA->id,
            [['playerOutId' => $starter->id, 'playerInId' => $subIn->id]],
        );

        $this->assertSame([$subIn->id], $lineup->pluck('id')->all());
    }
}
