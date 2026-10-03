<?php

namespace Tests\Feature;

use App\Models\AcademyPlayer;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\Team;
use App\Models\User;
use App\Modules\Academy\Services\YouthAcademyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for QA bugs A2 + A3 (academy).
 *
 * A2: poaching a rival youth player always returned 500 because
 *     PoachYouthPlayer read `$game->finances?->transfer_budget`, but
 *     Game::finances() is a HasMany (Collection) and game_finances has no
 *     such column — the budget lives in game_investments.transfer_budget
 *     (cents), via $game->currentInvestment.
 *
 * A3: academy players never developed: computeGrowth() applied round() to
 *     each matchday's 0.07–0.13 increment without accumulating fractions,
 *     so growth was always 0. Fractional growth now accumulates in
 *     academy_players.growth_progress; whole points move to overall_score
 *     once the accumulated progress crosses 1.0.
 */
class QaA2A3AcademyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['name' => 'QA A2A3 Test FC', 'country' => 'ES']);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => '2026-08-15',
            'reserve_team_id' => null,
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => 2026,
            'youth_academy_tier' => 3,
            'scouting_tier' => 2,
            'transfer_budget' => 0,
        ]);
    }

    private function makeAcademyPlayer(array $overrides = []): AcademyPlayer
    {
        return AcademyPlayer::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'name' => 'Canterana QA '.Str::random(6),
            'nationality' => ['Spain'],
            'date_of_birth' => Carbon::parse('2026-08-15')->subYears(17)->toDateString(),
            'position' => 'Central Midfield',
            'overall_score' => 60,
            'potential' => 80,
            'potential_low' => 75,
            'potential_high' => 85,
            'appeared_at' => '2026-08-15',
            'is_on_loan' => false,
            'is_jewel' => false,
            'joined_season' => 2026,
            'initial_overall' => 60,
        ], $overrides));
    }

    private function academyService(): YouthAcademyService
    {
        return app(YouthAcademyService::class);
    }

    // ------------------------------------------------------------------
    // A2: poach a rival youth player end-to-end
    // ------------------------------------------------------------------

    public function test_poach_youth_player_succeeds_and_deducts_fee_from_transfer_budget(): void
    {
        $rival = Team::factory()->create(['name' => 'QA A2A3 Rival', 'country' => 'ES']);
        // potential 60 -> refuseChance = 0 (rival never refuses), fee = €50,000
        $prospect = $this->makeAcademyPlayer([
            'team_id' => $rival->id,
            'potential' => 60,
            'name' => 'Perla Robable A2',
        ]);

        // €10M budget in cents on the current season's investment
        $this->game->currentInvestment->update(['transfer_budget' => 1_000_000_000]);

        $response = $this->actingAs($this->user)
            ->post(route('game.scouting.youth.poach', [$this->game->id, $prospect->id]));

        // No 500: success redirects to the youth scouting screen
        $response->assertRedirect(route('game.scouting.youth', $this->game->id));

        // The prospect now belongs to the user's academy
        $this->assertEquals($this->team->id, $prospect->fresh()->team_id);

        // Budget deducted by exactly the fee (€50,000 -> 5,000,000 cents)
        $this->assertEquals(
            1_000_000_000 - 5_000_000,
            $this->game->currentInvestment->fresh()->transfer_budget
        );

        // Social buzz was posted
        $this->assertDatabaseHas('social_posts', [
            'game_id' => $this->game->id,
            'context' => 'youth_poach',
        ]);
    }

    public function test_poach_youth_player_with_insufficient_budget_is_rejected(): void
    {
        $rival = Team::factory()->create(['name' => 'QA A2A3 Rival Pobre', 'country' => 'ES']);
        $prospect = $this->makeAcademyPlayer([
            'team_id' => $rival->id,
            'potential' => 60,
            'name' => 'Perla Cara A2',
        ]);

        // €100 budget: fee is €50,000 -> cannot afford
        $this->game->currentInvestment->update(['transfer_budget' => 10_000]);

        $response = $this->actingAs($this->user)
            ->from(route('game.scouting.youth', $this->game->id))
            ->post(route('game.scouting.youth.poach', [$this->game->id, $prospect->id]));

        $response->assertRedirect(route('game.scouting.youth', $this->game->id));
        $response->assertSessionHas('error');

        // Nothing moved, nothing deducted
        $this->assertEquals($rival->id, $prospect->fresh()->team_id);
        $this->assertEquals(10_000, $this->game->currentInvestment->fresh()->transfer_budget);
    }

    // ------------------------------------------------------------------
    // A3: academy players develop over matchdays
    // ------------------------------------------------------------------

    public function test_fractional_growth_accumulates_before_first_whole_point(): void
    {
        $p = $this->makeAcademyPlayer(['overall_score' => 55, 'potential' => 80]);

        $this->academyService()->developPlayers($this->game);

        $fresh = $p->fresh();
        // 25 * 0.25 / 38 ≈ 0.164 < 1: no whole point yet...
        $this->assertEquals(55, $fresh->overall_score);
        // ...but the fraction was persisted, not rounded away
        $this->assertGreaterThan(0, $fresh->growth_progress);
    }

    public function test_academy_player_develops_measurably_over_matchdays(): void
    {
        $p = $this->makeAcademyPlayer(['overall_score' => 55, 'potential' => 80]);
        $last = 55;

        for ($i = 0; $i < 60; $i++) {
            $this->academyService()->developPlayers($this->game);
            $current = $p->fresh()->overall_score;
            $this->assertGreaterThanOrEqual($last, $current, 'development must be monotonic');
            $this->assertLessThanOrEqual(80, $current, 'development must never exceed potential');
            $last = $current;
        }

        $final = $p->fresh()->overall_score;
        $this->assertGreaterThan(55, $final, 'player must have grown after 60 matchdays');
        // Design: ~25% of the gap per season; 60 matchdays ≈ 1.6 seasons of a
        // 25-point gap -> clearly measurable, still far from potential.
        $this->assertGreaterThanOrEqual(60, $final, 'growth must be measurable');
        $this->assertLessThan(80, $final, 'growth must stay coherent with potential');
    }

    public function test_academy_player_converges_toward_potential_without_overshoot(): void
    {
        $p = $this->makeAcademyPlayer(['overall_score' => 55, 'potential' => 80]);

        for ($i = 0; $i < 300; $i++) {
            $this->academyService()->developPlayers($this->game);
        }

        $final = $p->fresh();
        $this->assertLessThanOrEqual(80, $final->overall_score, 'must never exceed potential');
        $this->assertGreaterThan(70, $final->overall_score, 'must converge close to potential');
    }
}
