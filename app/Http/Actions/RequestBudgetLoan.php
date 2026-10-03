<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Finance\Services\BudgetLoanService;
use Illuminate\Http\Request;

class RequestBudgetLoan
{
    public function __construct(
        private readonly BudgetLoanService $loanService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $validated = $request->validate([
            // 32-bit: keep the *100 to cents within exact float/integer range;
            // euros are capped at 99999999.
            'amount' => 'required|numeric|min:1|max:99999999',
        ]);

        $amountInCents = (int) round($validated['amount'] * 100);

        try {
            $loan = $this->loanService->requestLoan($game, $amountInCents);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('game.club.finances', $gameId)
                ->with('error', __($e->getMessage()));
        }

        return redirect()->route('game.club.finances', $gameId)
            ->with('success', __('messages.budget_loan_approved', [
                'amount' => $loan->formatted_amount,
            ]));
    }
}
