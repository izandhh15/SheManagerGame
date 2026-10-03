<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SeasonSettlementProcessor;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Modules\Stadium\Services\NamingRightsService;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * R2 regression: SeasonSettlementProcessor must scope ledger reads to the
 * season window (July 1 → June 30) and must count wages of mid-season
 * departures. Before the fix:
 *  - calculateTransferIncome() / calculateCupBonusRevenue() summed the
 *    whole lifetime ledger, so old seasons' income was re-credited (and
 *    carried over) every year;
 *  - calculateActualWages() ignored players sold mid-season, inflating
 *    the surplus.
 */
class R2SettlementWindowTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(): Game
    {
        $team = Team::factory()->create();
        $game = Game::factory()->forTeam($team)->create(['season' => '2025']);

        GameFinances::create([
            'game_id' => $game->id,
            'season' => '2025',
            'projected_position' => 10,
            'projected_total_revenue' => 0,
            'projected_wages' => 0,
            'projected_operating_expenses' => 0,
            'projected_surplus' => 0,
            'projected_commercial_revenue' => 0,
            'projected_subsidy_revenue' => 0,
            'projected_solidarity_funds_revenue' => 0,
            'projected_season_ticket_revenue' => 0,
        ]);

        GameStanding::create([
            'game_id' => $game->id,
            'competition_id' => $game->competition_id,
            'team_id' => $game->team_id,
            'position' => 10,
            'played' => 38,
            'won' => 10,
            'drawn' => 8,
            'lost' => 20,
            'goals_for' => 40,
            'goals_against' => 60,
            'points' => 38,
        ]);

        return $game;
    }

    private function makeProcessor(): SeasonSettlementProcessor
    {
        $attendanceService = Mockery::mock(MatchAttendanceService::class);
        $seasonTicketPricingService = Mockery::mock(SeasonTicketPricingService::class);
        $namingRightsService = Mockery::mock(NamingRightsService::class);
        $namingRightsService->shouldReceive('settledRevenueForGame')->andReturn(0);

        return new SeasonSettlementProcessor($attendanceService, $seasonTicketPricingService, $namingRightsService);
    }

    private function record(Game $game, string $category, string $type, int $amount, string $date): void
    {
        FinancialTransaction::create([
            'game_id' => $game->id,
            'category' => $category,
            'type' => $type,
            'amount' => $amount,
            'description' => 'test',
            'transaction_date' => $date,
        ]);
    }

    public function test_prior_season_income_is_not_recredited(): void
    {
        $game = $this->makeGame();

        // Season window for season 2025: 2025-07-01 → 2026-06-30.
        $this->record($game, FinancialTransaction::CATEGORY_TRANSFER_IN, FinancialTransaction::TYPE_INCOME, 40_000_000_00, '2025-08-10');
        $this->record($game, FinancialTransaction::CATEGORY_TRANSFER_IN, FinancialTransaction::TYPE_INCOME, 99_000_000_00, '2025-05-01'); // prior season
        $this->record($game, FinancialTransaction::CATEGORY_CUP_BONUS, FinancialTransaction::TYPE_INCOME, 5_000_000_00, '2026-02-15');
        $this->record($game, FinancialTransaction::CATEGORY_CUP_BONUS, FinancialTransaction::TYPE_INCOME, 7_000_000_00, '2024-11-20'); // two seasons back

        $this->makeProcessor()->process(
            $game,
            new SeasonTransitionData(oldSeason: '2025', newSeason: '2026', competitionId: $game->competition_id),
        );

        $finances = $game->currentFinances->refresh();

        $this->assertSame(40_000_000_00, $finances->actual_transfer_income);
        $this->assertSame(5_000_000_00, $finances->actual_cup_bonus_revenue);
    }

    public function test_sold_player_wages_are_discounted_pro_rata(): void
    {
        $game = $this->makeGame();
        $aiTeam = Team::factory()->create();

        // Player on the squad all season: full annual wage counts.
        GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'annual_wage' => 1_200_000_00,
        ]);

        // Player sold mid-season (2026-01-01 = 6 months after 2025-07-01):
        // his row now sits at the buying team, with the completed outgoing
        // offer stamping the sale date. 6/12 of the wage must still count.
        $sold = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $aiTeam->id,
            'annual_wage' => 1_200_000_00,
        ]);
        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $sold->id,
            'offering_team_id' => $aiTeam->id,
            'selling_team_id' => $game->team_id,
            'offer_type' => TransferOffer::TYPE_LISTED,
            'transfer_fee' => 10_000_000_00,
            'status' => TransferOffer::STATUS_COMPLETED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'expires_at' => '2026-01-10',
            'game_date' => '2025-12-28',
            'resolved_at' => '2026-01-01',
        ]);

        $this->makeProcessor()->process(
            $game,
            new SeasonTransitionData(oldSeason: '2025', newSeason: '2026', competitionId: $game->competition_id),
        );

        $finances = $game->currentFinances->refresh();

        // 1.2M full season + ~1.2M * 6/12 for the sold player (Carbon 3
        // diffInMonths returns a float, so compare with a delta; without
        // the fix this would be exactly 1_200_000_00).
        $this->assertGreaterThan(1_200_000_00, $finances->actual_wages);
        $this->assertEqualsWithDelta(1_800_000_00, $finances->actual_wages, 1_000_000);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
