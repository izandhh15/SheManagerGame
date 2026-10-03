<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Manager\ManagerReputation;
use App\Modules\Manager\Services\ManagerReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R3 [ALTA]: JobApplicationService called
 * ManagerReputationService::getReputationLevel(), which did not exist →
 * "Call to undefined method" → 500 on GET and POST of the manager
 * job market. The method now exists and delegates to
 * ManagerReputation::levelFromPoints().
 */
class R3JobMarketReputationTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_reputation_level_exists_and_delegates_to_level_from_points(): void
    {
        $service = app(ManagerReputationService::class);

        $this->assertTrue(
            method_exists($service, 'getReputationLevel'),
            'ManagerReputationService::getReputationLevel() must exist'
        );

        $game = Game::factory()->create(['manager_reputation_points' => 250]);

        $this->assertSame(
            ManagerReputation::levelFromPoints(250),
            $service->getReputationLevel($game),
            'getReputationLevel() must delegate to ManagerReputation::levelFromPoints()'
        );

        // Zero points → local tier (the ?? 0 default path).
        $fresh = Game::factory()->create(['manager_reputation_points' => 0]);
        $this->assertSame('local', $service->getReputationLevel($fresh));
    }

    public function test_job_market_page_does_not_500(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'game_mode' => Game::MODE_CAREER_PRO,
        ]);

        $response = $this->actingAs($user)->get(route('game.job-market', $game->id));

        $this->assertNotEquals(500, $response->getStatusCode(), 'GET job market must not 500');
        $response->assertOk();
    }

    public function test_apply_for_job_does_not_500(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $target = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'game_mode' => Game::MODE_CAREER_PRO,
        ]);

        // Seed the RNG so the application is rejected deterministically:
        // seed 1234 gives mt_rand(1,100) = 76 > 50 → rejected at base 50%.
        mt_srand(1234);

        $response = $this->actingAs($user)
            ->post(route('game.job-market.apply', [$game->id, $target->id]));

        mt_srand();

        $this->assertNotEquals(500, $response->getStatusCode(), 'POST job application must not 500');
        $response->assertRedirect(route('game.job-market', $game->id));
        $this->assertDatabaseHas('manager_job_offers', [
            'game_id' => $game->id,
            'team_id' => $target->id,
        ]);
    }
}
