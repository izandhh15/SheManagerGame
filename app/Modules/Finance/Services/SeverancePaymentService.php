<?php

namespace App\Modules\Finance\Services;

use App\Models\BudgetLoan;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SeverancePaymentPlan;
use App\Modules\Notification\Services\NotificationService;
use App\Support\Money;

/**
 * Gestiona el pago de la carta de libertad (indemnización por rescisión).
 *
 * Tres formas de pago:
 * - lump_sum: pago único inmediato con cargo al presupuesto de fichajes.
 * - installments: a plazos (6 o 12 meses) con un pequeño interés.
 * - bank_loan: se pide un préstamo al banco por el importe y se paga de golpe.
 */
class SeverancePaymentService
{
    public const METHOD_LUMP_SUM = 'lump_sum';
    public const METHOD_INSTALLMENTS_6 = 'installments_6';
    public const METHOD_INSTALLMENTS_12 = 'installments_12';
    public const METHOD_BANK_LOAN = 'bank_loan';

    /** Interés total aplicado al pago a plazos (5%). */
    private const INSTALLMENT_INTEREST_RATE = 0.05;

    public function __construct(
        private readonly BudgetLoanService $budgetLoanService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Métodos de pago disponibles para una indemnización.
     *
     * @return array<array{key: string, label: string, detail: string}>
     */
    public function availableMethods(Game $game, int $severance): array
    {
        $methods = [
            [
                'key' => self::METHOD_LUMP_SUM,
                'label' => __('finances.severance_method_lump_sum'),
                'detail' => __('finances.severance_method_lump_sum_detail', ['amount' => Money::format($severance)]),
            ],
        ];

        if ($severance > 0) {
            foreach ([6, 12] as $months) {
                $total = $this->installmentTotal($severance, $months);
                $monthly = (int) ceil($total / $months);
                $methods[] = [
                    'key' => $months === 6 ? self::METHOD_INSTALLMENTS_6 : self::METHOD_INSTALLMENTS_12,
                    'label' => __('finances.severance_method_installments', ['months' => $months]),
                    'detail' => __('finances.severance_method_installments_detail', [
                        'monthly' => Money::format($monthly),
                        'total' => Money::format($total),
                    ]),
                ];
            }

            // Préstamo bancario: solo si no hay otro préstamo activo.
            if ($this->budgetLoanService->activeLoan($game) === null && $game->currentInvestment) {
                $maxLoan = $this->budgetLoanService->maxLoanAmount($game);
                if ($maxLoan >= $severance) {
                    $interestRate = config('finances.loan.interest_rate', 1500);
                    $repayment = (int) ($severance * (1 + $interestRate / 10000));
                    $methods[] = [
                        'key' => self::METHOD_BANK_LOAN,
                        'label' => __('finances.severance_method_bank_loan'),
                        'detail' => __('finances.severance_method_bank_loan_detail', [
                            'amount' => Money::format($severance),
                            'repayment' => Money::format($repayment),
                        ]),
                    ];
                }
            }
        }

        return $methods;
    }

    /**
     * Ejecuta el pago de la indemnización con el método elegido.
     *
     * @return array{error?: string, method?: string}
     */
    public function paySeverance(Game $game, ?GamePlayer $player, string $playerName, int $severance, string $method): array
    {
        if ($severance <= 0) {
            return ['method' => $method];
        }

        return match ($method) {
            self::METHOD_LUMP_SUM => $this->payLumpSum($game, $player, $playerName, $severance),
            self::METHOD_INSTALLMENTS_6 => $this->payInInstallments($game, $player, $playerName, $severance, 6),
            self::METHOD_INSTALLMENTS_12 => $this->payInInstallments($game, $player, $playerName, $severance, 12),
            self::METHOD_BANK_LOAN => $this->payWithBankLoan($game, $player, $playerName, $severance),
            default => ['error' => __('messages.severance_invalid_method')],
        };
    }

    private function payLumpSum(Game $game, ?GamePlayer $player, string $playerName, int $severance): array
    {
        $this->deductFromBudget($game, $severance);

        FinancialTransaction::recordExpense(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_SEVERANCE,
            amount: $severance,
            description: __('finances.tx_player_released', ['player' => $playerName]),
            transactionDate: $game->current_date->toDateString(),
            relatedPlayerId: $player?->id,
        );

        return ['method' => self::METHOD_LUMP_SUM];
    }

    private function payInInstallments(Game $game, ?GamePlayer $player, string $playerName, int $severance, int $months): array
    {
        $total = $this->installmentTotal($severance, $months);
        $monthly = (int) ceil($total / $months);

        // Primera cuota inmediata.
        $this->deductFromBudget($game, $monthly);

        $plan = SeverancePaymentPlan::create([
            'game_id' => $game->id,
            'game_player_id' => $player?->id,
            'player_name' => $playerName,
            'total_amount' => $total,
            'amount_paid' => $monthly,
            'monthly_amount' => $monthly,
            'months_total' => $months,
            'months_paid' => 1,
            'status' => $months <= 1 ? SeverancePaymentPlan::STATUS_COMPLETED : SeverancePaymentPlan::STATUS_ACTIVE,
            'next_due_date' => $game->current_date->copy()->addMonth()->toDateString(),
        ]);

        FinancialTransaction::recordExpense(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_SEVERANCE,
            amount: $monthly,
            description: __('finances.tx_severance_installment', [
                'player' => $playerName,
                'current' => 1,
                'total' => $months,
            ]),
            transactionDate: $game->current_date->toDateString(),
            relatedPlayerId: $player?->id,
        );

        $this->notificationService->create(
            game: $game,
            type: \App\Models\GameNotification::TYPE_SEVERANCE_PLAN_CREATED,
            title: __('notifications.severance_plan_title', ['player' => $playerName]),
            message: __('notifications.severance_plan_message', [
                'player' => $playerName,
                'monthly' => Money::format($monthly),
                'months' => $months,
                'total' => Money::format($total),
            ]),
            priority: \App\Models\GameNotification::PRIORITY_INFO,
        );

        return ['method' => $months === 6 ? self::METHOD_INSTALLMENTS_6 : self::METHOD_INSTALLMENTS_12];
    }

    private function payWithBankLoan(Game $game, ?GamePlayer $player, string $playerName, int $severance): array
    {
        if ($this->budgetLoanService->activeLoan($game) !== null) {
            return ['error' => __('messages.severance_loan_active')];
        }

        $investment = $game->currentInvestment;
        if (!$investment) {
            return ['error' => __('messages.severance_loan_unavailable')];
        }

        $interestRate = config('finances.loan.interest_rate', 1500); // basis points
        $repaymentAmount = (int) ($severance * (1 + $interestRate / 10000));

        $loan = BudgetLoan::create([
            'game_id' => $game->id,
            'season' => $game->season,
            'amount' => $severance,
            'interest_rate' => $interestRate,
            'repayment_amount' => $repaymentAmount,
            'status' => BudgetLoan::STATUS_ACTIVE,
        ]);

        // El préstamo entra en caja y de ahí se paga la indemnización de golpe.
        $investment->increment('transfer_budget', $severance);

        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_BUDGET_LOAN,
            amount: $severance,
            description: __('finances.tx_severance_loan_received', ['player' => $playerName]),
            transactionDate: $game->current_date->toDateString(),
        );

        $this->deductFromBudget($game, $severance);

        FinancialTransaction::recordExpense(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_SEVERANCE,
            amount: $severance,
            description: __('finances.tx_player_released', ['player' => $playerName]),
            transactionDate: $game->current_date->toDateString(),
            relatedPlayerId: $player?->id,
        );

        $this->notificationService->notifyBudgetLoanTaken(
            $game,
            $loan->formatted_amount,
            $loan->formatted_repayment_amount,
        );

        return ['method' => self::METHOD_BANK_LOAN];
    }

    private function installmentTotal(int $severance, int $months): int
    {
        return (int) ceil($severance * (1 + self::INSTALLMENT_INTEREST_RATE));
    }

    private function deductFromBudget(Game $game, int $amount): void
    {
        $game->currentInvestment?->decrement('transfer_budget', $amount);
    }
}
