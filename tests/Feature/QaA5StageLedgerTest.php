<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\TrainingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A5 residual: TrainingStageService::confirmClubStage descontaba el
 * transfer_budget sin registrar FinancialTransaction, así que el coste
 * del stage reaparecía en el carry-over de la temporada siguiente.
 */
class QaA5StageLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(): Game
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Stage Ledger WFC', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'game_mode' => Game::MODE_CAREER,
            'season' => '2026',
        ]);

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => $game->season,
            'transfer_budget' => 10_000_000_00,
            'scouting_tier' => 1,
        ]);

        GamePlayer::factory()->count(5)->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
        ]);

        return $game;
    }

    public function test_club_stage_records_ledger_expense(): void
    {
        $game = $this->makeGame();
        $service = app(TrainingStageService::class);

        $result = $service->confirmClubStage($game->refresh(), [
            'destination' => 'Portugal',
            'duration' => '1w',
            'intensity' => 'balanced',
            'focus' => 'physical',
        ]);

        $this->assertTrue($result['ok']);
        $cost = $result['cost'];
        $this->assertGreaterThan(0, $cost);

        $tx = FinancialTransaction::where('game_id', $game->id)
            ->where('category', FinancialTransaction::CATEGORY_TOUR)
            ->where('type', FinancialTransaction::TYPE_EXPENSE)
            ->first();

        $this->assertNotNull($tx, 'El stage de club debe registrar el gasto en el ledger');
        $this->assertSame($cost * 100, (int) $tx->amount);
    }

    public function test_club_stage_expense_counts_in_carryover_categories(): void
    {
        // La categoría usada por el stage debe estar entre las que resta
        // BudgetProjectionService::getPreviousSeasonNetPosition().
        $ref = new \ReflectionClass(\App\Modules\Finance\Services\BudgetProjectionService::class);
        $categories = $ref->getConstant('MIDSEASON_EXPENSE_CATEGORIES');
        $this->assertContains(FinancialTransaction::CATEGORY_TOUR, $categories);
    }
}
