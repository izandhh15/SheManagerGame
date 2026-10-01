<?php

namespace App\Modules\Finance\Listeners;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\MatchAttendance;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Stadium\Services\MatchdayPricingService;
use Illuminate\Support\Facades\DB;

/**
 * Books matchday revenue (tickets, shirts, merch, bars) when a club-mode
 * home fixture is finalized. Registered AFTER EnsureMatchAttendance so the
 * attendance figure already exists.
 *
 * Only the manager's own home games generate revenue: away fixtures and
 * national-team games (federation budget, no transfer budget) are skipped.
 * Idempotent per match: a revenue row per category is written only once,
 * so re-finalization never double-counts.
 */
class RecordMatchdayRevenue
{
    public function __construct(
        private readonly MatchdayPricingService $pricing,
    ) {}

    public function handle(MatchFinalized $event): void
    {
        $match = $event->match;
        $game = $event->game;

        if (! $this->isRevenueMatch($match, $game)) {
            return;
        }

        $attendanceRow = MatchAttendance::where('game_match_id', $match->id)->first();
        if ($attendanceRow === null || (int) $attendanceRow->attendance <= 0) {
            return;
        }

        $prices = $this->pricing->prices($game);
        $revenue = $this->pricing->revenueForAttendance((int) $attendanceRow->attendance, $prices);

        $lines = [
            FinancialTransaction::CATEGORY_MATCHDAY_TICKETS => $revenue['tickets'],
            FinancialTransaction::CATEGORY_MATCHDAY_SHIRTS => $revenue['shirts'],
            FinancialTransaction::CATEGORY_MATCHDAY_MERCH => $revenue['merch'],
            FinancialTransaction::CATEGORY_MATCHDAY_BARS => $revenue['bars'],
        ];

        $totalCents = 0;

        DB::transaction(function () use ($game, $match, $lines, &$totalCents) {
            foreach ($lines as $category => $euros) {
                // Skip duplicates: a match is booked exactly once per line.
                $already = FinancialTransaction::where('game_id', $game->id)
                    ->where('category', $category)
                    ->where('description', 'like', '%#' . $match->id . '%')
                    ->exists();

                if ($already || $euros <= 0) {
                    continue;
                }

                $cents = $euros * 100;
                $totalCents += $cents;

                FinancialTransaction::recordIncome(
                    gameId: $game->id,
                    category: $category,
                    amount: $cents,
                    description: $this->describe($category, $match, $euros),
                    transactionDate: $game->current_date->toDateString(),
                );
            }

            if ($totalCents > 0) {
                $game->currentInvestment->increment('transfer_budget', $totalCents);
            }
        });
    }

    private function isRevenueMatch(GameMatch $match, Game $game): bool
    {
        // Club mode only: the manager runs a club budget, not a federation.
        if ($game->isTournamentMode()) {
            return false;
        }

        if (! $match->played || $match->game_id !== $game->id) {
            return false;
        }

        if ($match->home_team_id !== $game->team_id) {
            return false;
        }

        return $game->currentInvestment !== null;
    }

    private function describe(string $category, GameMatch $match, int $euros): string
    {
        $label = match ($category) {
            FinancialTransaction::CATEGORY_MATCHDAY_TICKETS => __('finances.category_matchday_tickets'),
            FinancialTransaction::CATEGORY_MATCHDAY_SHIRTS => __('finances.category_matchday_shirts'),
            FinancialTransaction::CATEGORY_MATCHDAY_MERCH => __('finances.category_matchday_merch'),
            FinancialTransaction::CATEGORY_MATCHDAY_BARS => __('finances.category_matchday_bars'),
            default => $category,
        };

        $opponent = $match->awayTeam?->name ?? '';

        return __('game.matchday_revenue_line', [
            'label' => $label,
            'opponent' => $opponent,
            'amount' => number_format($euros, 0, ',', '.'),
            'match_id' => $match->id,
        ]);
    }
}
