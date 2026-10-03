<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Stadium\Services\NationalVenueOrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 6: NationalVenueOrganizationService::payClub() and grantRebate()
 * must credit the club's spendable transfer_budget in addition to the
 * ledger entry (like RecordMatchdayRevenue does) — otherwise the money is
 * visible in the books but not spendable.
 */
class NationalVenueFinanceTest extends TestCase
{
    use RefreshDatabase;

    private Game $clubGame;
    private Game $nationalGame;
    private Team $clubTeam;
    private GameInvestment $investment;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->create(['id' => 'WNATIONS']);

        $user = User::factory()->create();
        $this->clubTeam = Team::factory()->create(['country' => 'ES', 'stadium_name' => 'Club Stadium']);

        // Dual mode: club game is the primary, national game the secondary.
        $this->clubGame = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->clubTeam->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
        $this->nationalGame = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => Team::factory()->create(['type' => 'national', 'country' => 'ES'])->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
            'game_mode' => Game::MODE_TOURNAMENT,
            'linked_game_id' => $this->clubGame->id,
        ]);

        $this->investment = GameInvestment::create([
            'game_id' => $this->clubGame->id,
            'season' => 2026,
            'transfer_budget' => 10_000_000_00,
        ]);
    }

    public function test_pay_club_credits_transfer_budget(): void
    {
        $match = GameMatch::factory()->create([
            'game_id' => $this->nationalGame->id,
            'competition_id' => 'WNATIONS',
            'home_team_id' => $this->nationalGame->team_id,
            'away_team_id' => Team::factory()->create(['type' => 'national'])->id,
            'played' => false,
            'scheduled_date' => '2026-09-10',
        ]);

        $service = app(NationalVenueOrganizationService::class);
        $method = new \ReflectionMethod($service, 'payClub');
        $method->setAccessible(true);
        $method->invoke($service, $this->nationalGame, $this->clubTeam, 500_000, $match);

        $this->assertDatabaseHas('financial_transactions', [
            'game_id' => $this->clubGame->id,
            'category' => 'venue_fee',
            'amount' => 500_000_00,
        ]);
        $this->assertSame(
            10_000_000_00 + 500_000_00,
            (int) $this->investment->fresh()->transfer_budget
        );
    }

    public function test_grant_rebate_credits_transfer_budget(): void
    {
        $service = app(NationalVenueOrganizationService::class);
        $method = new \ReflectionMethod($service, 'grantRebate');
        $method->setAccessible(true);
        $method->invoke($service, $this->nationalGame, 'Real Madrid', 'Santiago Bernabéu', 200_000);

        $this->assertDatabaseHas('financial_transactions', [
            'game_id' => $this->clubGame->id,
            'category' => 'venue_fee',
            'amount' => 200_000_00,
        ]);
        $this->assertSame(
            10_000_000_00 + 200_000_00,
            (int) $this->investment->fresh()->transfer_budget
        );
    }
}
