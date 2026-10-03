<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\MatchAttendance;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Finance\Listeners\RecordMatchdayRevenue;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Season\Processors\SeasonSettlementProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for QA bug A7: the season settlement computed matchday
 * revenue with the legacy VirtuaFC per-seat formula
 * (SeasonSettlementProcessor::calculateMatchdayRevenue) instead of summing
 * the ACTUAL ledger bookings made by RecordMatchdayRevenue, diverging ~12x
 * from the real figure and ignoring the manager-set ticket prices.
 *
 * The settlement must now equal the sum of the matchday income categories
 * in the FinancialTransaction ledger, windowed to the current season.
 */
class QaA7SettlementLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function buildGame(): Game
    {
        $team = Team::factory()->create(['country' => 'ES']);
        $competition = Competition::factory()->league()->create(['tier' => 1]);
        $game = Game::factory()->create([
            'team_id' => $team->id,
            'competition_id' => $competition->id,
            'season' => '2026',
            'current_date' => '2026-09-01',
            'country' => 'ES',
            'ticket_price' => 20,
            'shirt_price' => 70,
            'merch_price' => 20,
            'bar_price' => 4,
        ]);
        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'reputation_level' => 'established',
            'base_reputation_level' => 'established',
            'reputation_points' => 50,
        ]);
        GameInvestment::create([
            'game_id' => $game->id,
            'season' => 2026,
            'available_surplus' => 0,
            'youth_academy_amount' => 0, 'youth_academy_tier' => 1,
            'medical_amount' => 0, 'medical_tier' => 1,
            'scouting_amount' => 0, 'scouting_tier' => 1,
            'transfer_budget' => 0,
        ]);

        return $game;
    }

    private function playHomeMatches(Game $game, int $count, int $attendance): void
    {
        $listener = app(RecordMatchdayRevenue::class);
        $competitionId = $game->competition_id;
        for ($i = 1; $i <= $count; $i++) {
            $match = GameMatch::factory()->create([
                'game_id' => $game->id,
                'competition_id' => $competitionId,
                'home_team_id' => $game->team_id,
                'round_number' => $i,
                'played' => true,
            ]);
            MatchAttendance::create([
                'game_id' => $game->id,
                'game_match_id' => $match->id,
                'attendance' => $attendance,
                'capacity_at_match' => 10_000,
            ]);
            $listener->handle(new MatchFinalized($match, $game));
        }
    }

    private function ledgerMatchdayRevenue(Game $game): int
    {
        return (int) FinancialTransaction::where('game_id', $game->id)
            ->whereIn('category', [
                FinancialTransaction::CATEGORY_MATCHDAY_TICKETS,
                FinancialTransaction::CATEGORY_MATCHDAY_SHIRTS,
                FinancialTransaction::CATEGORY_MATCHDAY_MERCH,
                FinancialTransaction::CATEGORY_MATCHDAY_BARS,
            ])
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');
    }

    private function settlementMatchdayRevenue(Game $game): int
    {
        $processor = app(SeasonSettlementProcessor::class);
        $ref = new \ReflectionMethod($processor, 'calculateMatchdayRevenue');

        return (int) $ref->invoke($processor, $game);
    }

    public function test_settlement_matchday_revenue_matches_ledger(): void
    {
        // Replicates the QA repro: 15 home matches x 5,000 spectators.
        $game = $this->buildGame();
        $this->playHomeMatches($game, 15, 5_000);

        $ledgerMatchday = $this->ledgerMatchdayRevenue($game);
        $settlementMatchday = $this->settlementMatchdayRevenue($game);

        fwrite(STDERR, "\n[A7] ledger matchday total: €" . number_format($ledgerMatchday / 100, 0, ',', '.')
            . " | settlement matchday: €" . number_format($settlementMatchday / 100, 0, ',', '.') . "\n");

        $this->assertGreaterThan(0, $ledgerMatchday, 'The ledger should hold matchday bookings.');

        // Divergence must be under 1%: settlement == ledger.
        $this->assertEqualsWithDelta(
            $ledgerMatchday,
            $settlementMatchday,
            max(1, (int) ($ledgerMatchday * 0.01)),
            'Settlement matchday revenue diverges from the booked ledger figure (bug A7).'
        );
    }

    public function test_manager_ticket_prices_flow_into_settlement(): void
    {
        // Same gates, doubled ticket price -> the settlement figure must
        // follow the ledger (the legacy formula ignored prices entirely).
        $game = $this->buildGame();
        $game->update(['ticket_price' => 40]);
        $this->playHomeMatches($game, 15, 5_000);

        $ledgerMatchday = $this->ledgerMatchdayRevenue($game);
        $settlementMatchday = $this->settlementMatchdayRevenue($game);

        $this->assertEqualsWithDelta(
            $ledgerMatchday,
            $settlementMatchday,
            max(1, (int) ($ledgerMatchday * 0.01)),
            'Settlement must reflect the manager-set ticket prices via the ledger.'
        );
    }

    public function test_settlement_ignores_previous_season_gate(): void
    {
        // The Game row persists across seasons, so the settlement must only
        // count THIS season's gate (July 1 -> June 30), not earlier bookings.
        $game = $this->buildGame();
        $this->playHomeMatches($game, 15, 5_000);

        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_MATCHDAY_TICKETS,
            amount: 9_999_000_00,
            description: 'Stale gate from the previous season',
            transactionDate: '2026-05-15',
        );

        $currentSeasonLedger = (int) FinancialTransaction::where('game_id', $game->id)
            ->whereIn('category', [
                FinancialTransaction::CATEGORY_MATCHDAY_TICKETS,
                FinancialTransaction::CATEGORY_MATCHDAY_SHIRTS,
                FinancialTransaction::CATEGORY_MATCHDAY_MERCH,
                FinancialTransaction::CATEGORY_MATCHDAY_BARS,
            ])
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->whereBetween('transaction_date', ['2026-07-01', '2027-06-30'])
            ->sum('amount');

        $settlementMatchday = $this->settlementMatchdayRevenue($game);

        $this->assertSame(
            $currentSeasonLedger,
            $settlementMatchday,
            'Settlement must not double-count the previous season\'s gate.'
        );
    }
}
