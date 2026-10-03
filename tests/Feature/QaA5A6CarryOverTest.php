<?php

namespace Tests\Feature;

use App\Models\BudgetLoan;
use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\Team;
use App\Modules\Finance\Services\BudgetProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression tests for QA Fase 2 (altos) bugs A5 + A6 — season carry-over.
 *
 * A5: getPreviousSeasonNetPosition() ignored mid-season expenses
 *     (venue_rent, tour_cost, agent_fee, stadium, severance) that left the
 *     spendable transfer_budget — the money reappeared every season.
 * A6: a budget loan's principal was charged twice — once as transfer
 *     spending in the net position, once in full via the subsidy repayment.
 *
 * Mirrors the agent-8 repros in ~/workspace/shemanager/qa/workers/agent-8/.
 */
class QaA5A6CarryOverTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(array $overrides = []): Game
    {
        $team = Team::factory()->create(['country' => 'ES', 'name' => 'Valencia CF Femení QA-A5A6']);
        $competition = Competition::factory()->league()->create(['tier' => 1]);

        return Game::factory()->create(array_merge([
            'team_id' => $team->id,
            'competition_id' => $competition->id,
            'season' => '2026',
            'current_date' => '2026-09-01',
            'country' => 'ES',
        ], $overrides));
    }

    private function makeInvestment(Game $game, int $transferBudgetCents): GameInvestment
    {
        return GameInvestment::create([
            'game_id' => $game->id,
            'season' => (int) $game->season,
            'available_surplus' => $transferBudgetCents,
            'youth_academy_amount' => 0,
            'youth_academy_tier' => 1,
            'medical_amount' => 0,
            'medical_tier' => 1,
            'scouting_amount' => 0,
            'scouting_tier' => 1,
            'transfer_budget' => $transferBudgetCents,
        ]);
    }

    /** Previous-season finances row with a settled actual surplus. */
    private function makeSettledFinances(Game $game, int $season, int $actualSurplus, int $actualTotalRevenue): GameFinances
    {
        return GameFinances::create([
            'game_id' => $game->id,
            'season' => $season,
            'actual_surplus' => $actualSurplus,
            'actual_total_revenue' => $actualTotalRevenue,
            'carried_surplus' => 0,
            'carried_debt' => 0,
            'projected_surplus' => $actualSurplus,
        ]);
    }

    private function recordSeasonExpense(Game $game, string $category, int $amountCents, string $date = '2026-10-20'): void
    {
        FinancialTransaction::recordExpense(
            gameId: $game->id,
            category: $category,
            amount: $amountCents,
            description: "QA-A5A6 {$category}",
            transactionDate: $date,
        );
    }

    private function projectionService(): BudgetProjectionService
    {
        return app(BudgetProjectionService::class);
    }

    // ── A5: mid-season expenses must not reappear ──────────────────────────

    public function test_carryover_deducts_venue_rent_expense(): void
    {
        $game = $this->makeGame(['season' => '2027']);
        $this->makeSettledFinances($game, 2026, 10_000_000_00, 50_000_000_00);
        $this->makeInvestment($game, 5_000_000_00);

        // Mid-season: rent a stadium for €1M, paid from the transfer budget.
        $game->currentInvestment->decrement('transfer_budget', 1_000_000_00);
        $this->recordSeasonExpense($game, FinancialTransaction::CATEGORY_VENUE_RENT, 1_000_000_00);

        $carried = $this->projectionService()->getCarriedSurplus($game);

        // The €1M is gone from the club, so it must not be carried forward.
        $this->assertSame(10_000_000_00 - 1_000_000_00, $carried);
    }

    #[DataProvider('midseasonExpenseCategories')]
    public function test_carryover_deducts_other_midseason_expenses(string $category): void
    {
        $game = $this->makeGame(['season' => '2027']);
        $this->makeSettledFinances($game, 2026, 10_000_000_00, 50_000_000_00);
        $this->makeInvestment($game, 5_000_000_00);

        $game->currentInvestment->decrement('transfer_budget', 500_000_00);
        $this->recordSeasonExpense($game, $category, 500_000_00, '2026-11-05');

        $carried = $this->projectionService()->getCarriedSurplus($game);

        $this->assertSame(
            10_000_000_00 - 500_000_00,
            $carried,
            "Expense category {$category} was not deducted from the carried surplus."
        );
    }

    public static function midseasonExpenseCategories(): array
    {
        return [
            'tour' => [FinancialTransaction::CATEGORY_TOUR],
            'agent fee' => [FinancialTransaction::CATEGORY_AGENT_FEE],
            'stadium works' => [FinancialTransaction::CATEGORY_STADIUM],
            'severance' => [FinancialTransaction::CATEGORY_SEVERANCE],
        ];
    }

    public function test_carryover_deducts_all_midseason_spending_combined(): void
    {
        $game = $this->makeGame(['season' => '2027']);
        $this->makeSettledFinances($game, 2026, 10_000_000_00, 50_000_000_00);
        $this->makeInvestment($game, 8_000_000_00);

        // A busy season: transfers + rent + tour + agent fee + works + severance.
        $this->recordSeasonExpense($game, FinancialTransaction::CATEGORY_TRANSFER_OUT, 2_000_000_00, '2026-08-15');
        $this->recordSeasonExpense($game, FinancialTransaction::CATEGORY_VENUE_RENT, 1_000_000_00, '2026-10-20');
        $this->recordSeasonExpense($game, FinancialTransaction::CATEGORY_TOUR, 500_000_00, '2026-07-10');
        $this->recordSeasonExpense($game, FinancialTransaction::CATEGORY_AGENT_FEE, 200_000_00, '2026-09-01');
        $this->recordSeasonExpense($game, FinancialTransaction::CATEGORY_STADIUM, 300_000_00, '2027-01-15');
        $this->recordSeasonExpense($game, FinancialTransaction::CATEGORY_SEVERANCE, 400_000_00, '2027-02-01');

        $carried = $this->projectionService()->getCarriedSurplus($game);

        // income − expenses = balance: every cent spent stays spent.
        $this->assertSame(10_000_000_00 - 4_400_000_00, $carried);
    }

    public function test_carryover_still_deducts_transfer_purchases(): void
    {
        $game = $this->makeGame(['season' => '2027']);
        $this->makeSettledFinances($game, 2026, 10_000_000_00, 50_000_000_00);

        $this->recordSeasonExpense($game, FinancialTransaction::CATEGORY_TRANSFER_OUT, 2_000_000_00, '2026-09-15');

        $carried = $this->projectionService()->getCarriedSurplus($game);

        // Control case: pre-existing behaviour must keep working.
        $this->assertSame(10_000_000_00 - 2_000_000_00, $carried);
    }

    // ── A6: the loan principal must be counted exactly once ───────────────

    public function test_budget_loan_is_not_double_charged(): void
    {
        $game = $this->makeGame(['season' => '2027']);
        // Season 2026: surplus €10M. During 2026 the manager borrowed €1M
        // (15% interest => repays €1.15M) and spent the full €1M on transfers.
        $this->makeSettledFinances($game, 2026, 10_000_000_00, 50_000_000_00);

        $loan = BudgetLoan::create([
            'game_id' => $game->id,
            'season' => 2026,
            'amount' => 1_000_000_00,
            'interest_rate' => 1500,
            'repayment_amount' => 1_150_000_00,
            'status' => BudgetLoan::STATUS_REPAID,
        ]);

        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_BUDGET_LOAN,
            amount: $loan->amount,
            description: 'QA-A5A6 loan received',
            transactionDate: '2026-08-10',
        );
        $this->recordSeasonExpense($game, FinancialTransaction::CATEGORY_TRANSFER_OUT, 1_000_000_00, '2026-08-20');

        $service = $this->projectionService();
        $netPosition = $service->getCarriedSurplus($game); // max(net,0)
        $repayment = $service->getPreviousSeasonLoanRepayment($game);

        // True cash: +10M surplus +1M loan −1M transfers = 10M net position;
        // the deployed principal was already counted as spending, so only
        // the interest is still owed via the subsidy. Total cost of the loan
        // must be exactly the interest (€150K), not €2.15M.
        $totalCostOfLoan = (10_000_000_00 - $netPosition) + $repayment;

        $this->assertSame(10_000_000_00, $netPosition);
        $this->assertSame(150_000_00, $repayment);
        $this->assertSame(150_000_00, $totalCostOfLoan);
    }

    public function test_unspent_loan_principal_is_repaid_in_full(): void
    {
        $game = $this->makeGame(['season' => '2027']);
        // Season 2026: surplus €10M, borrowed €1M at 15% but spent nothing.
        $this->makeSettledFinances($game, 2026, 10_000_000_00, 50_000_000_00);

        BudgetLoan::create([
            'game_id' => $game->id,
            'season' => 2026,
            'amount' => 1_000_000_00,
            'interest_rate' => 1500,
            'repayment_amount' => 1_150_000_00,
            'status' => BudgetLoan::STATUS_REPAID,
        ]);

        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_BUDGET_LOAN,
            amount: 1_000_000_00,
            description: 'QA-A5A6 loan received, unspent',
            transactionDate: '2026-08-10',
        );

        $service = $this->projectionService();
        $netPosition = $service->getCarriedSurplus($game);
        $repayment = $service->getPreviousSeasonLoanRepayment($game);

        // The unspent €1M must not evaporate (it is carried forward), but the
        // hoarded principal is clawed back in full — borrow-and-hoard must
        // never mint free money. income − expenses = balance: the loan costs
        // exactly its interest either way.
        $this->assertSame(11_000_000_00, $netPosition);
        $this->assertSame(1_150_000_00, $repayment);
        $this->assertSame(150_000_00, (10_000_000_00 - $netPosition) + $repayment);
    }
}
