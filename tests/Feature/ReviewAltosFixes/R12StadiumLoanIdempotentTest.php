<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameStadiumProject;
use App\Models\StadiumLoan;
use App\Models\Team;
use App\Modules\Finance\Services\StadiumLoanService;
use App\Modules\Stadium\Enums\StadiumLoanStatus;
use App\Modules\Stadium\Enums\StadiumProjectFinancing;
use App\Modules\Stadium\Enums\StadiumProjectStatus;
use App\Modules\Stadium\Enums\StadiumProjectType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R12 regression: StadiumLoanService::billAnnualPayment() must be
 * idempotent per season. Before the fix, a re-execution of the billing
 * processor (crash between charge and checkpoint, concurrent advance poll)
 * charged the annual instalment twice with no recovery path.
 */
class R12StadiumLoanIdempotentTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private StadiumLoan $loan;

    private StadiumLoanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $team = Team::factory()->create();
        $this->game = Game::factory()->forTeam($team)->create(['season' => '2025']);

        $project = GameStadiumProject::create([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'type' => StadiumProjectType::Rebuild,
            'status' => StadiumProjectStatus::InProgress,
            'target_capacity' => 30_000,
            'committed_season' => 2024,
            'committed_date' => $this->game->current_date,
            'completion_date' => $this->game->current_date,
            'total_cost_cents' => 450_000_000_00,
            'financing' => StadiumProjectFinancing::Loan,
            'paid_cents' => 0,
        ]);

        $this->loan = StadiumLoan::create([
            'game_id' => $this->game->id,
            'stadium_project_id' => $project->id,
            'principal_cents' => 100_000_000_00,
            'term_years' => 10,
            'interest_rate_bps' => 400,
            'remaining_principal_cents' => 100_000_000_00,
            'season_started' => 2025,
            'status' => StadiumLoanStatus::Active,
        ]);

        $this->service = app(StadiumLoanService::class);
    }

    public function test_double_billing_charges_only_once(): void
    {
        $expectedPayment = 10_000_000_00 + (int) round(100_000_000_00 * 400 / 10000);

        $first = $this->service->billAnnualPayment($this->loan, $this->game);
        $this->assertSame($expectedPayment, $first);

        $second = $this->service->billAnnualPayment($this->loan, $this->game);
        $this->assertSame(0, $second);

        $this->loan->refresh();

        // Principal reduced once, billed season stamped.
        $this->assertSame(90_000_000_00, $this->loan->remaining_principal_cents);
        $this->assertSame(2025, $this->loan->last_billed_season);
        $this->assertSame(StadiumLoanStatus::Active, $this->loan->status);

        // Exactly one ledger row for the instalment.
        $this->assertSame(1, FinancialTransaction::where('game_id', $this->game->id)
            ->where('category', FinancialTransaction::CATEGORY_LOAN_REPAYMENT)
            ->count());
    }

    public function test_next_season_bills_again(): void
    {
        $this->service->billAnnualPayment($this->loan, $this->game);

        $this->game->update(['season' => '2026']);
        $this->loan->refresh();

        $payment = $this->service->billAnnualPayment($this->loan, $this->game);

        $this->assertGreaterThan(0, $payment);
        $this->assertSame(2026, $this->loan->refresh()->last_billed_season);
        $this->assertSame(2, FinancialTransaction::where('game_id', $this->game->id)
            ->where('category', FinancialTransaction::CATEGORY_LOAN_REPAYMENT)
            ->count());
    }
}
