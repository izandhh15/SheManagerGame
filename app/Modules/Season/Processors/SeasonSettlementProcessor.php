<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Finance\Services\BudgetLoanService;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Modules\Stadium\Services\NamingRightsService;
use App\Modules\Commercial\Services\SponsorService;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use App\Models\BudgetLoan;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameSponsorDeal;
use App\Models\GameStanding;
use App\Models\Loan;
use App\Models\TransferOffer;
use Carbon\Carbon;

/**
 * Calculates actual season revenue and settles the finances.
 * Computes variance between projected and actual, carrying debt if needed.
 * Runs after archive but before standings reset so we can use final position.
 */
class SeasonSettlementProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly MatchAttendanceService $matchAttendanceService,
        private readonly SeasonTicketPricingService $seasonTicketPricingService,
        private readonly NamingRightsService $namingRightsService,
        // Optional so existing manual constructions (tests) keep working;
        // the container always injects the real service in production.
        private readonly ?SponsorService $sponsorService = null,
    ) {}

    public function priority(): int
    {
        return 60;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $finances = $game->currentFinances;

        // If no finances record exists, skip settlement
        if (!$finances) {
            return $data;
        }

        // Get actual final position
        $standing = GameStanding::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        $actualPosition = $standing->position ?? $finances->projected_position;

        // Calculate actual revenues
        $actualTvRevenue = $this->calculateTvRevenue($actualPosition, $game);
        $actualMatchdayRevenue = $this->calculateMatchdayRevenue($game);
        $actualCommercialRevenue = $this->calculateCommercialRevenue(
            $finances->projected_commercial_revenue, $actualPosition
        );
        $actualTransferIncome = $this->calculateTransferIncome($game);
        $actualCupBonusRevenue = $this->calculateCupBonusRevenue($game);

        // Naming-rights income settles proportional to the realised gate, so
        // a season of empty seats earns the sponsor's lower end.
        $actualNamingRightsRevenue = $this->namingRightsService->settledRevenueForGame($game);

        // Shirt / ad-board income settles at the fixed annual fee — the
        // sponsor pays the same regardless of results.
        $actualShirtSponsorRevenue = $this->sponsorService?->settledRevenueForGame($game, GameSponsorDeal::SLOT_SHIRT) ?? 0;
        $actualAdBoardRevenue = $this->sponsorService?->settledRevenueForGame($game, GameSponsorDeal::SLOT_AD_BOARD) ?? 0;

        // Guaranteed income — same amount as projected. Season ticket
        // revenue is collected up front at the season's start so it stays
        // locked to the projected figure (no variance).
        $actualSubsidyRevenue = $finances->projected_subsidy_revenue;
        $actualSolidarityFundsRevenue = $finances->projected_solidarity_funds_revenue;
        $actualSeasonTicketRevenue = $finances->projected_season_ticket_revenue;

        $actualTotalRevenue = $actualTvRevenue
            + $actualMatchdayRevenue
            + $actualSeasonTicketRevenue
            + $actualCommercialRevenue
            + $actualNamingRightsRevenue
            + $actualShirtSponsorRevenue
            + $actualAdBoardRevenue
            + $actualSubsidyRevenue
            + $actualSolidarityFundsRevenue
            + $actualCupBonusRevenue
            + $actualTransferIncome;

        // Calculate actual wages (pro-rated for owned players + loan salary transactions)
        $actualWages = $this->calculateActualWages($game);

        // Operating expenses are fixed costs — same as projected
        $actualOperatingExpenses = $finances->projected_operating_expenses;

        // Calculate actual surplus
        $actualSurplus = $actualTotalRevenue - $actualWages - $actualOperatingExpenses;

        // Calculate variance (difference between actual and projected surplus)
        $variance = $actualSurplus - $finances->projected_surplus;

        // Update finances with actuals
        $finances->update([
            'actual_tv_revenue' => $actualTvRevenue,
            'actual_solidarity_funds_revenue' => $actualSolidarityFundsRevenue,
            'actual_cup_bonus_revenue' => $actualCupBonusRevenue,
            'actual_matchday_revenue' => $actualMatchdayRevenue,
            'actual_season_ticket_revenue' => $actualSeasonTicketRevenue,
            'actual_commercial_revenue' => $actualCommercialRevenue,
            'actual_naming_rights_revenue' => $actualNamingRightsRevenue,
            'actual_shirt_sponsor_revenue' => $actualShirtSponsorRevenue,
            'actual_ad_board_revenue' => $actualAdBoardRevenue,
            'actual_subsidy_revenue' => $actualSubsidyRevenue,
            'actual_transfer_income' => $actualTransferIncome,
            'net_transfer_result' => $this->calculateNetTransferResult($game),
            'actual_total_revenue' => $actualTotalRevenue,
            'actual_wages' => $actualWages,
            'actual_operating_expenses' => $actualOperatingExpenses,
            'actual_surplus' => $actualSurplus,
            'variance' => $variance,
        ]);

        // Repay any active budget loan
        $loanRepayment = 0;
        $activeLoan = BudgetLoan::where('game_id', $game->id)
            ->where('status', BudgetLoan::STATUS_ACTIVE)
            ->first();

        if ($activeLoan) {
            $loanService = app(BudgetLoanService::class);
            $loanRepayment = $loanService->repayLoan($activeLoan);
        }

        // Store in metadata for season-end display
        $data->setMetadata('finances', [
            'projected_position' => $finances->projected_position,
            'actual_position' => $actualPosition,
            'projected_total_revenue' => $finances->projected_total_revenue,
            'actual_total_revenue' => $actualTotalRevenue,
            'projected_surplus' => $finances->projected_surplus,
            'actual_surplus' => $actualSurplus,
            'variance' => $variance,
            'has_debt' => $variance < 0,
            'loan_repayment' => $loanRepayment,
        ]);

        return $data;
    }

    private function calculateTvRevenue(int $position, Game $game): int|float
    {
        $league = $game->competition;
        $config = $league->getConfig();

        return $config->getTvRevenue($position);
    }

    /**
     * Sum the ACTUAL matchday revenue booked to the ledger this season.
     *
     * RecordMatchdayRevenue books four income lines per finalized home
     * fixture (matchday_tickets, matchday_shirts, matchday_merch,
     * matchday_bars) using the manager's own ticket prices, and credits
     * every cent to the spendable transfer budget. The settlement must
     * sum those same ledger rows instead of re-estimating with the legacy
     * per-seat rate from VirtuaFC — otherwise the season-end books (and
     * the carried surplus) are computed on a fictional gate while the
     * manager has already received and spent the real money mid-season.
     *
     * Windowed to the current season (July 1 -> June 30) because the Game
     * row persists across seasons and accumulates transactions: without
     * the window, earlier seasons' gate would be double-counted. Mirrors
     * the season scoping of calculateNetTransferResult().
     */
    private function calculateMatchdayRevenue(Game $game): int
    {
        [$seasonStart, $seasonEnd] = $this->seasonWindow($game);

        return (int) FinancialTransaction::where('game_id', $game->id)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->whereIn('category', [
                FinancialTransaction::CATEGORY_MATCHDAY_TICKETS,
                FinancialTransaction::CATEGORY_MATCHDAY_SHIRTS,
                FinancialTransaction::CATEGORY_MATCHDAY_MERCH,
                FinancialTransaction::CATEGORY_MATCHDAY_BARS,
            ])
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');
    }

    /**
     * Apply position-based growth multiplier to projected commercial revenue.
     */
    private function calculateCommercialRevenue(int $projected, int $position): int
    {
        $thresholds = config('finances.commercial_growth', []);
        $multiplier = 1.0;

        foreach ($thresholds as $maxPosition => $factor) {
            if ($position <= $maxPosition) {
                $multiplier = $factor;
                break;
            }
        }

        return (int) ($projected * $multiplier);
    }

    /**
     * Season window for ledger queries: July 1 of the season year through
     * June 30 of the following year. The Game row persists across seasons
     * and accumulates transactions, so every ledger read must be scoped to
     * this window or earlier seasons get counted (and carried over) again.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function seasonWindow(Game $game): array
    {
        $seasonYear = (int) $game->season;

        return [
            Carbon::createFromDate($seasonYear, 7, 1),
            Carbon::createFromDate($seasonYear + 1, 6, 30),
        ];
    }

    /**
     * Transfer-sale income for THIS season only. Sums the
     * `transfer_in` ledger rows windowed to July 1 → June 30: without the
     * window, sales from earlier seasons were re-credited every year and
     * the carry-over booked them into the budget again (free money).
     */
    private function calculateTransferIncome(Game $game): int
    {
        [$seasonStart, $seasonEnd] = $this->seasonWindow($game);

        // Get transfer income from financial transactions (player sales)
        return FinancialTransaction::where('game_id', $game->id)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_IN)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');
    }

    /**
     * Cup-bonus revenue for THIS season only, windowed July 1 → June 30
     * for the same reason as calculateTransferIncome(): unwindowed, old
     * bonuses re-appeared in every later season's surplus.
     */
    private function calculateCupBonusRevenue(Game $game): int
    {
        [$seasonStart, $seasonEnd] = $this->seasonWindow($game);

        return FinancialTransaction::where('game_id', $game->id)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->where('category', FinancialTransaction::CATEGORY_CUP_BONUS)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');
    }

    /**
     * Net player-trading result for THIS season (sales − purchases), windowed
     * to the season's July 1 → June 30 span. Stored per season so the trailing
     * player-trading allowance ("plusvalías") that widens the salary cap reads
     * a clean, season-scoped history. May be negative for a net buyer.
     */
    private function calculateNetTransferResult(Game $game): int
    {
        [$seasonStart, $seasonEnd] = $this->seasonWindow($game);

        $sales = (int) FinancialTransaction::where('game_id', $game->id)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_IN)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');

        $purchases = (int) FinancialTransaction::where('game_id', $game->id)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_OUT)
            ->where('type', FinancialTransaction::TYPE_EXPENSE)
            ->sum('amount');

        return $sales - $purchases;
    }

    /**
     * Actual wage bill for the season: pro-rated wages for the players on
     * the squad at season close (mid-season joiners pro-rated from their
     * arrival date), PLUS pro-rated wages for players SOLD mid-season
     * (they left the squad query but their salary was paid up to the sale
     * date), PLUS loan salary expenses. Everything is windowed to the
     * season's July 1 → June 30 span.
     */
    private function calculateActualWages(Game $game): int
    {
        // Get all players currently on the squad, excluding loaned-in players
        // (their salary is tracked via CATEGORY_LOAN transactions instead)
        $loanedInPlayerIds = Loan::where('game_id', $game->id)
            ->where('loan_team_id', $game->team_id)
            ->pluck('game_player_id');

        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNotIn('id', $loanedInPlayerIds)
            ->get();

        // Season runs from July 1 to June 30 (12 months)
        [$seasonStart, $seasonEnd] = $this->seasonWindow($game);
        $totalMonths = 12;

        // Batch-load mid-season join dates from transfers
        $playerIds = $players->pluck('id');

        $transferDates = TransferOffer::where('game_id', $game->id)
            ->whereIn('game_player_id', $playerIds)
            ->where('status', TransferOffer::STATUS_COMPLETED)
            ->incoming()
            ->whereBetween('resolved_at', [$seasonStart, $seasonEnd])
            ->pluck('resolved_at', 'game_player_id');

        $totalWages = 0;

        foreach ($players as $player) {
            $joinDate = $transferDates[$player->id] ?? null;

            // No join date found = player was here all season (initial squad, youth academy, pre-contract)
            if (!$joinDate || Carbon::parse($joinDate)->lte($seasonStart)) {
                $totalWages += $player->annual_wage;
                continue;
            }

            // Joined during season, pro-rate
            $parsedJoinDate = Carbon::parse($joinDate);
            if ($parsedJoinDate->lt($seasonEnd)) {
                $monthsAtClub = $parsedJoinDate->diffInMonths($seasonEnd);
                $proRatedWage = (int) ($player->annual_wage * ($monthsAtClub / $totalMonths));
                $totalWages += $proRatedWage;
            }
        }

        // Wages of players sold mid-season: the squad query above no longer
        // sees them (their team_id moved to the buyer), but their salary was
        // paid from season start until the sale date. Prorate it from the
        // transfer's resolved_at (permanent sales only — loaned-out players'
        // wages are paid by the borrowing club).
        $soldTransfers = TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_COMPLETED)
            ->outgoing()
            ->where('offer_type', '!=', TransferOffer::TYPE_LOAN_OUT)
            ->whereBetween('resolved_at', [$seasonStart, $seasonEnd])
            ->with('gamePlayer')
            ->get();

        foreach ($soldTransfers as $offer) {
            $wage = $offer->gamePlayer?->annual_wage;

            if (! $wage) {
                continue;
            }

            $saleDate = Carbon::parse($offer->resolved_at);

            if ($saleDate->lte($seasonStart)) {
                // Sold before the season began: no wage paid this season.
                continue;
            }

            $monthsAtClub = $seasonStart->diffInMonths($saleDate);
            $totalWages += (int) ($wage * ($monthsAtClub / $totalMonths));
        }

        // Add loan salary expenses (recorded as transactions when loans completed),
        // windowed to the season so loans from prior seasons aren't counted again.
        $loanExpenses = FinancialTransaction::where('game_id', $game->id)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->where('category', FinancialTransaction::CATEGORY_LOAN)
            ->where('type', FinancialTransaction::TYPE_EXPENSE)
            ->sum('amount');

        return $totalWages + $loanExpenses;
    }
}
