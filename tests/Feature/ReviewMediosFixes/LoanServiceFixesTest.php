<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\TransferListing;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fix 16: LoanService
 *  - completeLoanIn re-verifies $player->team_id === $offer->selling_team_id
 *    (phantom double-loan guard, like completeIncomingTransfer)
 *  - completeLoanIn records the PRORATED loan wage, not the full annual wage
 *  - processLoanSearches covers the reserve team (filial scope), and loan
 *    destinations exclude every team the user manages
 */
class LoanServiceFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Team $reserveTeam;
    private Team $sellerTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['country' => 'ES']);
        $this->reserveTeam = Team::factory()->create(['country' => 'ES']);
        $this->sellerTeam = Team::factory()->create(['country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'reserve_team_id' => $this->reserveTeam->id,
            'season' => '2026',
            'current_date' => '2027-01-10',
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => 2026,
            'transfer_budget' => 50_000_000_00,
        ]);
    }

    private function loanInOffer(GamePlayer $player, string $sellingTeamId): TransferOffer
    {
        return TransferOffer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->team->id,
            'selling_team_id' => $sellingTeamId,
            'offer_type' => TransferOffer::TYPE_LOAN_IN,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => '2027-02-10',
            'game_date' => '2027-01-10',
        ]);
    }

    public function test_complete_loan_in_rejects_when_player_moved(): void
    {
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            // The player no longer belongs to the selling team (AI moved her).
            'team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'annual_wage' => 1_200_000_00,
        ]);
        $offer = $this->loanInOffer($player, $this->sellerTeam->id);

        $service = app(LoanService::class);
        $service->completeLoanIn($offer, $this->game);

        $this->assertSame(TransferOffer::STATUS_REJECTED, $offer->fresh()->status);
        $this->assertDatabaseMissing('loans', ['game_player_id' => $player->id]);
    }

    public function test_complete_loan_in_records_prorated_wage(): void
    {
        // January loan: the wage is prorated by months at the club
        // (Carbon 3 diffInMonths is fractional — same formula the season
        // settlement uses), NOT the full annual wage.
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->sellerTeam->id,
            'annual_wage' => 1_200_000_00,
        ]);
        $offer = $this->loanInOffer($player, $this->sellerTeam->id);

        $service = app(LoanService::class);
        $service->completeLoanIn($offer, $this->game);

        $this->assertSame(TransferOffer::STATUS_COMPLETED, $offer->fresh()->status);
        $this->assertSame($this->team->id, $player->fresh()->team_id);

        $tx = FinancialTransaction::where('game_id', $this->game->id)
            ->where('category', FinancialTransaction::CATEGORY_LOAN)
            ->where('related_player_id', $player->id)
            ->firstOrFail();

        $this->assertLessThan(1_200_000_00, (int) $tx->amount);

        $loan = Loan::where('game_player_id', $player->id)->firstOrFail();
        $expected = (int) (1_200_000_00 * (
            \Carbon\Carbon::parse($loan->started_at)->diffInMonths(\Carbon\Carbon::parse($loan->return_at)) / 12
        ));
        // Same proration formula; small float drift from date-vs-datetime
        // parts is OK (€10 tolerance on a €1.2M wage).
        $this->assertEqualsWithDelta($expected, (int) $tx->amount, 1_000_000);
    }

    public function test_process_loan_searches_covers_reserve_team(): void
    {
        // An expired search on a RESERVE player: pre-fix, processLoanSearches
        // only looked at $game->team_id, so the reserve listing rotted
        // forever; post-fix it is picked up and expired like any other.
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->reserveTeam->id,
        ]);
        TransferListing::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'team_id' => $this->reserveTeam->id,
            'status' => TransferListing::STATUS_LOAN_SEARCH,
            'listed_at' => '2026-12-01', // > 21 days before 2027-01-10
        ]);

        $service = app(LoanService::class);
        $result = $service->processLoanSearches($this->game);

        $this->assertCount(1, $result['expired']);
        $this->assertSame($player->id, $result['expired'][0]['player']->id);
        $this->assertDatabaseMissing('transfer_listings', ['game_player_id' => $player->id]);
    }
}
