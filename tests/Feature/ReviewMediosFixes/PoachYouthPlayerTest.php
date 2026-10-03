<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Http\Actions\PoachYouthPlayer;
use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fix 18: PoachYouthPlayer
 *  - a rival refusal now costs a scouting fee (anti spam-click)
 *  - the budget check + decrement run atomically inside the transaction
 *    (two concurrent poaches can't drive the budget negative)
 *
 * potential = 60 → refuseChance = 0 → the poach always succeeds, which
 * makes the success path deterministic in tests.
 */
class PoachYouthPlayerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Team $rivalTeam;
    private Game $game;
    private GameInvestment $investment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['country' => 'ES']);
        $this->rivalTeam = Team::factory()->create(['country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $this->investment = GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => 2026,
            'transfer_budget' => 50_000_000_00,
        ]);
    }

    private function prospect(int $potential = 60): AcademyPlayer
    {
        return AcademyPlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'team_id' => $this->rivalTeam->id,
            'name' => 'Rival Prospect',
            'nationality' => ['ES'],
            'date_of_birth' => '2009-02-01',
            'position' => 'Central Midfield',
            'overall_score' => 58,
            'potential' => $potential,
            'potential_low' => 55,
            'potential_high' => 65,
            'appeared_at' => '2026-08-15',
        ]);
    }

    private function request(): Request
    {
        $request = Request::create("/game/{$this->game->id}/poach/x", 'POST');
        $request->setUserResolver(fn () => $this->user);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    public function test_successful_poach_moves_player_and_charges_fee(): void
    {
        // potential 60 → fee €50k → 5 000 000 cents.
        $prospect = $this->prospect(60);
        $before = (int) $this->investment->fresh()->transfer_budget;

        $action = app(PoachYouthPlayer::class);
        $response = $action($this->request(), $this->game->id, $prospect->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->has('success'));
        $this->assertSame($this->team->id, $prospect->fresh()->team_id);
        $this->assertSame($before - 5_000_000, (int) $this->investment->fresh()->transfer_budget);
    }

    public function test_poach_without_budget_is_rejected(): void
    {
        $this->investment->update(['transfer_budget' => 1_000_000]);
        $prospect = $this->prospect(60);

        $action = app(PoachYouthPlayer::class);
        $response = $action($this->request(), $this->game->id, $prospect->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->getSession()->has('error'));
        $this->assertSame($this->rivalTeam->id, $prospect->fresh()->team_id);
        $this->assertSame(1_000_000, (int) $this->investment->fresh()->transfer_budget);
    }

    public function test_second_poach_of_same_player_is_rejected(): void
    {
        $prospect = $this->prospect(60);

        $action = app(PoachYouthPlayer::class);
        $action($this->request(), $this->game->id, $prospect->id);

        // Already ours: the firstOrFail lookup (team_id != user's) fails.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $action($this->request(), $this->game->id, $prospect->id);
    }
}
