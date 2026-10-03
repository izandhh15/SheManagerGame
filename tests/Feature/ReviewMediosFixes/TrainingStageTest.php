<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\TrainingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 12: TrainingStageService
 *  - the youth age cutoff uses $game->current_date, not Carbon::now()
 *  - the "one stage per season" guard runs inside the transaction under a
 *    row lock (double submit can't charge/apply twice)
 */
class TrainingStageTest extends TestCase
{
    use RefreshDatabase;

    private function clubGame(int $budgetCents = 100_000_000): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
            'federation_budget' => 10_000_000,
        ]);
        $investment = GameInvestment::create([
            'game_id' => $game->id,
            'season' => 2026,
            'transfer_budget' => $budgetCents,
        ]);

        return [$game, $investment];
    }

    private function config(): array
    {
        return [
            'destination' => 'Spain',
            'duration' => '1w',
            'intensity' => 'balanced',
            'focus' => 'youth',
        ];
    }

    public function test_youth_cutoff_uses_game_date_not_real_date(): void
    {
        // Game date 2028-08-01: a player born 2006-06-01 is 22 in game time
        // (no youth boost), but only 20 on the real calendar (2026-10-03) —
        // the old Carbon::now() code would have boosted her.
        [$game, $investment] = $this->clubGame();
        $game->update(['current_date' => '2028-08-01']);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'date_of_birth' => '2006-06-01',
            'overall_score' => 70,
        ]);

        $service = app(TrainingStageService::class);
        $result = $service->confirmClubStage($game->fresh(), $this->config());

        $this->assertTrue($result['ok']);
        $this->assertSame(70, (int) $player->fresh()->overall_score);
    }

    public function test_youth_boost_applies_when_young_in_game_time(): void
    {
        // Game date 2024-08-01: born 2003-06-01 → 21 in game time (boost),
        // but 23 on the real calendar — the old code would have skipped her.
        [$game, $investment] = $this->clubGame();
        $game->update(['current_date' => '2024-08-01']);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'date_of_birth' => '2003-06-01',
            'overall_score' => 70,
        ]);

        $service = app(TrainingStageService::class);
        $result = $service->confirmClubStage($game->fresh(), $this->config());

        $this->assertTrue($result['ok']);
        // youth focus boost = 2 × 1.0 (1w effect multiplier) = 2
        $this->assertSame(72, (int) $player->fresh()->overall_score);
    }

    public function test_double_confirm_charges_only_once(): void
    {
        [$game, $investment] = $this->clubGame();
        $before = (int) $investment->transfer_budget;

        $service = app(TrainingStageService::class);
        $first = $service->confirmClubStage($game->fresh(), $this->config());
        $second = $service->confirmClubStage($game->fresh(), $this->config());

        $this->assertTrue($first['ok']);
        $this->assertFalse($second['ok']);
        $this->assertSame($before - $first['cost'] * 100, (int) $investment->fresh()->transfer_budget);
    }

    public function test_national_stage_double_confirm_charges_federation_once(): void
    {
        [$game] = $this->clubGame();
        $game->update(['game_mode' => Game::MODE_TOURNAMENT]);
        $before = (int) $game->federation_budget;

        $service = app(TrainingStageService::class);
        $first = $service->confirmStage($game->fresh(), $this->config());
        $second = $service->confirmStage($game->fresh(), $this->config());

        $this->assertTrue($first['ok']);
        $this->assertFalse($second['ok']);
        $this->assertSame($before - $first['cost'], (int) $game->fresh()->federation_budget);
    }
}
