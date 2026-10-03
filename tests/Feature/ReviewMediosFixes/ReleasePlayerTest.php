<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\TransferListing;
use App\Models\User;
use App\Modules\Finance\Services\SeverancePaymentService;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fixes 13-14:
 *  - ContractService::releasePlayer() pays + releases in one transaction
 *  - applyPendingWages() uses ownedByTeam() like the listing, so renewals
 *    agreed for loaned-out players are actually applied
 */
class ReleasePlayerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Game $game;
    private GameInvestment $investment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $this->investment = GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => 2026,
            'transfer_budget' => 50_000_000_00,
        ]);
    }

    private function squadPlayer(array $overrides = []): GamePlayer
    {
        return GamePlayer::factory()->create(array_merge([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'contract_until' => '2028-06-30',
            'annual_wage' => 1_000_000_00,
        ], $overrides));
    }

    public function test_release_player_pays_and_releases_atomically(): void
    {
        // 18 players so the squad-minimum guard (17) passes.
        for ($i = 0; $i < 17; $i++) {
            $this->squadPlayer();
        }
        $target = $this->squadPlayer();

        TransferListing::create([
            'game_id' => $this->game->id,
            'game_player_id' => $target->id,
            'team_id' => $this->team->id,
            'status' => TransferListing::STATUS_LISTED,
            'listed_at' => '2026-08-15',
        ]);

        $before = (int) $this->investment->fresh()->transfer_budget;
        $service = app(ContractService::class);

        $result = $service->releasePlayer(
            $this->game,
            $target,
            SeverancePaymentService::METHOD_LUMP_SUM
        );

        $this->assertArrayNotHasKey('error', $result);
        $this->assertNull($target->fresh()->team_id);
        $this->assertNull($target->fresh()->number);
        // Severance was charged (50% of remaining wages).
        $this->assertLessThan($before, (int) $this->investment->fresh()->transfer_budget);
        $this->assertSame($before - (int) $this->investment->fresh()->transfer_budget, (int) $result['severance']);
        $this->assertDatabaseHas('financial_transactions', [
            'game_id' => $this->game->id,
            'category' => FinancialTransaction::CATEGORY_SEVERANCE,
            'related_player_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('transfer_listings', ['game_player_id' => $target->id]);
    }

    public function test_release_with_invalid_method_changes_nothing(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->squadPlayer();
        }
        $target = $this->squadPlayer();
        $before = (int) $this->investment->fresh()->transfer_budget;

        $service = app(ContractService::class);
        $result = $service->releasePlayer($this->game, $target, 'not_a_method');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame($this->team->id, $target->fresh()->team_id);
        $this->assertSame($before, (int) $this->investment->fresh()->transfer_budget);
        $this->assertDatabaseMissing('financial_transactions', [
            'game_id' => $this->game->id,
            'category' => FinancialTransaction::CATEGORY_SEVERANCE,
            'related_player_id' => $target->id,
        ]);
    }

    public function test_apply_pending_wages_covers_loaned_out_players(): void
    {
        $otherTeam = Team::factory()->create(['country' => 'ES']);

        // Player loaned out: physically at another club, still owned.
        $loanedOut = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $otherTeam->id,
            'annual_wage' => 1_000_000_00,
            'pending_annual_wage' => 1_500_000_00,
        ]);
        Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $loanedOut->id,
            'parent_team_id' => $this->team->id,
            'loan_team_id' => $otherTeam->id,
            'started_at' => '2026-08-01',
            'return_at' => '2027-06-30',
            'status' => Loan::STATUS_ACTIVE,
        ]);

        // Player at the club with a pending renewal.
        $atClub = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'annual_wage' => 2_000_000_00,
            'pending_annual_wage' => 2_500_000_00,
        ]);

        $service = app(ContractService::class);
        $applied = $service->applyPendingWages($this->game);

        $this->assertCount(2, $applied);
        $this->assertSame(1_500_000_00, (int) $loanedOut->fresh()->annual_wage);
        $this->assertNull($loanedOut->fresh()->pending_annual_wage);
        $this->assertSame(2_500_000_00, (int) $atClub->fresh()->annual_wage);
        $this->assertNull($atClub->fresh()->pending_annual_wage);
    }
}
