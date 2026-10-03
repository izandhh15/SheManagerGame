<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\BudgetLoan;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\User;
use App\Modules\Finance\Services\BudgetLoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 7: BudgetLoanService::repayLoan() records the repayment
 * (principal + interest) in the ledger, mirroring the income entry made
 * by requestLoan().
 */
class BudgetLoanLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_repay_loan_records_ledger_expense(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $loan = BudgetLoan::create([
            'game_id' => $game->id,
            'season' => '2026',
            'amount' => 100_000_000,
            'interest_rate' => 1500,
            'repayment_amount' => 115_000_000,
            'status' => BudgetLoan::STATUS_ACTIVE,
        ]);

        $service = app(BudgetLoanService::class);
        $repaid = $service->repayLoan($loan);

        $this->assertSame(115_000_000, $repaid);
        $this->assertSame(BudgetLoan::STATUS_REPAID, $loan->fresh()->status);
        $this->assertDatabaseHas('financial_transactions', [
            'game_id' => $game->id,
            'type' => FinancialTransaction::TYPE_EXPENSE,
            'category' => FinancialTransaction::CATEGORY_LOAN_REPAYMENT,
            'amount' => 115_000_000,
        ]);
    }
}
