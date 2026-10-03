<?php

namespace App\Modules\Finance\Listeners;

use App\Models\FinancialTransaction;
use App\Models\GameNotification;
use App\Models\SeverancePaymentPlan;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Cobra la cuota mensual de los planes de pago de indemnizaciones
 * (carta de libertad a plazos) cuando vence.
 */
class ProcessSeveranceInstallments
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $game = $event->game;

        $duePlans = SeverancePaymentPlan::where('game_id', $game->id)
            ->where('status', SeverancePaymentPlan::STATUS_ACTIVE)
            ->where('next_due_date', '<=', $event->newDate->toDateString())
            ->get();

        foreach ($duePlans as $plan) {
            $this->chargeInstallment($game, $plan, $event->newDate);
        }
    }

    private function chargeInstallment($game, SeverancePaymentPlan $plan, $date): void
    {
        // All four writes (budget decrement, plan counters, status/date,
        // ledger entry, completion notice) go in one transaction so a
        // failure can't leave a half-charged installment behind.
        DB::transaction(function () use ($game, $plan, $date) {
            $this->doChargeInstallment($game, $plan, $date);
        });
    }

    private function doChargeInstallment($game, SeverancePaymentPlan $plan, $date): void
    {
        $amount = min($plan->monthly_amount, $plan->remaining_amount);

        // Cargo al presupuesto de fichajes.
        $game->currentInvestment?->decrement('transfer_budget', $amount);

        $plan->increment('amount_paid', $amount);
        $plan->increment('months_paid');

        $isLast = $plan->months_paid >= $plan->months_total || $plan->fresh()->remaining_amount <= 0;

        $plan->update([
            'status' => $isLast ? SeverancePaymentPlan::STATUS_COMPLETED : SeverancePaymentPlan::STATUS_ACTIVE,
            'next_due_date' => $isLast ? $plan->next_due_date : $date->copy()->addMonth()->toDateString(),
        ]);

        FinancialTransaction::recordExpense(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_SEVERANCE,
            amount: $amount,
            description: __('finances.tx_severance_installment', [
                'player' => $plan->player_name,
                'current' => $plan->months_paid,
                'total' => $plan->months_total,
            ]),
            transactionDate: $date->toDateString(),
            relatedPlayerId: $plan->game_player_id,
        );

        if ($isLast) {
            $this->notificationService->create(
                game: $game,
                type: GameNotification::TYPE_SEVERANCE_PLAN_COMPLETED,
                title: __('notifications.severance_plan_completed_title', ['player' => $plan->player_name]),
                message: __('notifications.severance_plan_completed_message', [
                    'player' => $plan->player_name,
                    'total' => Money::format($plan->total_amount),
                ]),
                priority: GameNotification::PRIORITY_INFO,
            );
        }
    }
}
