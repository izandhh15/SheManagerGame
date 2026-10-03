<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\BudgetLoan;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\User;
use App\Modules\Finance\Services\BudgetLoanService;
use App\Modules\Finance\Services\SeverancePaymentService;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * BAJA validación: SeverancePaymentService::payWithBankLoan() solo
 * comprobaba activeLoan() y creaba el préstamo a mano, eludiendo
 * canRequestLoan(), el mínimo de 500K y el máximo del 10% de
 * BudgetLoanService::requestLoan().
 */
class SeveranceLoanGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function invokePayWithBankLoan(
        SeverancePaymentService $service,
        Game $game,
        int $severance
    ): array {
        $method = new ReflectionMethod(SeverancePaymentService::class, 'payWithBankLoan');
        $method->setAccessible(true);

        return $method->invoke($service, $game, null, 'Test Player', $severance);
    }

    private function serviceWithMockedLoan(
        bool $canRequest,
        int $maxLoan = 0
    ): SeverancePaymentService {
        $loanService = $this->createStub(BudgetLoanService::class);
        $loanService->method('canRequestLoan')->willReturn($canRequest);
        $loanService->method('maxLoanAmount')->willReturn($maxLoan);

        return new SeverancePaymentService($loanService, app(NotificationService::class));
    }

    public function test_blocks_when_loan_not_available(): void
    {
        $game = Game::factory()->make();
        $service = $this->serviceWithMockedLoan(false);

        $result = $this->invokePayWithBankLoan($service, $game, 100_000_000);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(__('messages.severance_loan_unavailable'), $result['error']);
        $this->assertSame(0, BudgetLoan::count());
    }

    public function test_blocks_severance_below_loan_minimum(): void
    {
        $game = Game::factory()->make();
        $service = $this->serviceWithMockedLoan(true, 500_000_000);

        // Por debajo del mínimo que aplica requestLoan().
        $minimum = (int) config('finances.loan.minimum', 50_000_000);
        $result = $this->invokePayWithBankLoan($service, $game, $minimum - 1);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(__('messages.loan_below_minimum'), $result['error']);
        $this->assertSame(0, BudgetLoan::count());
    }

    public function test_blocks_severance_above_loan_maximum(): void
    {
        $game = Game::factory()->make();
        $service = $this->serviceWithMockedLoan(true, 100_000_000);

        $result = $this->invokePayWithBankLoan($service, $game, 100_000_001);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(__('messages.loan_exceeds_maximum'), $result['error']);
        $this->assertSame(0, BudgetLoan::count());
    }

    public function test_creates_loan_at_exact_limits(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
        GameInvestment::create([
            'game_id' => $game->id,
            'season' => $game->season,
            'transfer_budget' => 10_000_000_000,
        ]);

        // En los límites exactos (mínimo 500K, máximo = importe) pasa los guards.
        $service = $this->serviceWithMockedLoan(true, 50_000_000);

        $result = $this->invokePayWithBankLoan($service, $game, 50_000_000);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame(SeverancePaymentService::METHOD_BANK_LOAN, $result['method']);
        $this->assertDatabaseHas('budget_loans', [
            'game_id' => $game->id,
            'amount' => 50_000_000,
            'status' => BudgetLoan::STATUS_ACTIVE,
        ]);
    }
}
