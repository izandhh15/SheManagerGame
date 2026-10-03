<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Transfer\Services\ExploreService;
use App\Support\SqlLike;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA validación/sql-raw: los LIKE de búsqueda interpolaban el input
 * sin escapar %/_ (ExploreService, AdminUsers, AdminWaitlist). Ahora
 * comparten App\Support\SqlLike::escape().
 */
class SqlLikeEscapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_escape_neutralizes_wildcards(): void
    {
        $this->assertSame('\\%', SqlLike::escape('%'));
        $this->assertSame('\\_', SqlLike::escape('_'));
        $this->assertSame('\\\\', SqlLike::escape('\\'));
        $this->assertSame('100\\% real', SqlLike::escape('100% real'));
        $this->assertSame('plain text', SqlLike::escape('plain text'));
    }

    public function test_explore_search_treats_percent_literally(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $literal = GamePlayer::factory()->forGame($game)->forTeam($team)->create(['name' => 'Ana 100% Real']);
        $decoy = GamePlayer::factory()->forGame($game)->forTeam($team)->create(['name' => 'Ana 100X Real']);

        $result = app(ExploreService::class)->advancedSearch($game, ['name' => '100%']);

        $names = $result['players']->pluck('name')->all();
        $this->assertContains($literal->name, $names);
        $this->assertNotContains($decoy->name, $names);
    }

    public function test_explore_search_treats_underscore_literally(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $literal = GamePlayer::factory()->forGame($game)->forTeam($team)->create(['name' => 'Mar_a Test']);
        $decoy = GamePlayer::factory()->forGame($game)->forTeam($team)->create(['name' => 'MarXa Test']);

        $result = app(ExploreService::class)->advancedSearch($game, ['name' => 'mar_a']);

        $names = $result['players']->pluck('name')->all();
        $this->assertContains($literal->name, $names);
        $this->assertNotContains($decoy->name, $names);
    }
}
