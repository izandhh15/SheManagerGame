<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\MatchAttendance;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\User;
use App\Modules\Finance\Listeners\RecordMatchdayRevenue;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Season\Services\PreseasonOpponentService;
use App\Modules\Season\Services\PreseasonTourService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preseason tours: organizing a tour costs budget up front, adds club
 * prestige (reputation points), and makes tour home friendlies pay
 * noticeably more matchday revenue than a normal friendly.
 */
class PreseasonTourTest extends TestCase
{
    use RefreshDatabase;

    /** Local pgsql test DB (see phpunit.xml); independent of TestCase's override. */
    protected $connectionsToTransact = ['pgsql'];

    private User $user;
    private Team $playerTeam;
    private Team $opponent;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->playerTeam = Team::factory()->create(['name' => 'Player Team', 'country' => 'ES']);
        $this->opponent = Team::factory()->create(['name' => 'Opponent Team', 'country' => 'ES']);

        ClubProfile::create([
            'team_id' => $this->playerTeam->id,
            'reputation_level' => ClubProfile::REPUTATION_ELITE,
        ]);

        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);

        $this->game = $this->makeGame();
    }

    private function makeGame(): Game
    {
        $game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-07-01',
            'pre_season' => true,
            'preseason_opponents_pending' => true,
            'setup_completed_at' => now(),
            'needs_new_season_setup' => false,
            'needs_welcome' => false,
        ]);

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => 2026,
            'transfer_budget' => 1_000_000_00,
            'scouting_tier' => 1,
        ]);

        return $game;
    }

    private function makePlayedHomeFriendly(Game $game, int $roundNumber = 1): GameMatch
    {
        $match = GameMatch::create([
            'game_id' => $game->id,
            'competition_id' => PreseasonOpponentService::PRESEASON_COMPETITION_ID,
            'home_team_id' => $game->team_id,
            'away_team_id' => $this->opponent->id,
            'scheduled_date' => '2026-07-10',
            'round_number' => $roundNumber,
            'played' => true,
        ]);

        MatchAttendance::create([
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'attendance' => 8000,
            'capacity_at_match' => 12000,
        ]);

        return $match;
    }

    public function test_tour_is_rejected_for_non_elite_clubs(): void
    {
        $service = app(PreseasonTourService::class);

        // The fixture team is elite; a modest club must be refused.
        $smallTeam = Team::factory()->create(['name' => 'Small Team', 'country' => 'ES']);
        ClubProfile::create([
            'team_id' => $smallTeam->id,
            'reputation_level' => ClubProfile::REPUTATION_MODEST,
        ]);

        $smallGame = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $smallTeam->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-07-01',
            'pre_season' => true,
            'preseason_opponents_pending' => true,
            'setup_completed_at' => now(),
            'needs_new_season_setup' => false,
            'needs_welcome' => false,
        ]);

        $this->assertFalse($service->isEligible($smallGame));
        $this->assertTrue($service->isEligible($this->game));

        $result = $service->organize($smallGame, 'usa');

        $this->assertFalse($result['ok']);
        $this->assertSame(__('game.preseason_tour_not_eligible'), $result['message']);
        $this->assertNull($smallGame->fresh()->preseason_tour);
    }

    public function test_organize_tour_charges_cost_records_expense_and_awards_prestige(): void
    {        $result = app(PreseasonTourService::class)->organize($this->game, 'usa');

        $this->assertTrue($result['ok']);
        $this->game->refresh();

        $this->assertSame('usa', $this->game->preseason_tour['destination']);
        $this->assertSame(2.2, $this->game->preseason_tour['revenue_multiplier']);

        // Cost charged against the club budget (350.000 € in cents).
        $this->assertSame(1_000_000_00 - 350_000_00, (int) $this->game->currentInvestment->transfer_budget);

        $this->assertTrue(
            FinancialTransaction::where('game_id', $this->game->id)
                ->where('category', FinancialTransaction::CATEGORY_TOUR)
                ->where('amount', 350_000_00)
                ->exists()
        );

        // Prestige: reputation points added to the club's game-scoped row.
        $reputation = TeamReputation::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->first();

        $this->assertNotNull($reputation);
        $this->assertSame(60, (int) $reputation->reputation_points);
    }

    public function test_only_one_tour_per_preseason(): void
    {
        $service = app(PreseasonTourService::class);

        $this->assertTrue($service->organize($this->game, 'usa')['ok']);

        $result = $service->organize($this->game, 'mexico');

        $this->assertFalse($result['ok']);
        $this->game->refresh();
        $this->assertSame('usa', $this->game->preseason_tour['destination']);

        // Still only charged once.
        $this->assertSame(1_000_000_00 - 350_000_00, (int) $this->game->currentInvestment->transfer_budget);
    }

    public function test_tour_is_rejected_with_an_unknown_destination(): void
    {
        $result = app(PreseasonTourService::class)->organize($this->game, 'atlantis');

        $this->assertFalse($result['ok']);
        $this->assertNull($this->game->fresh()->preseason_tour);
    }

    public function test_tour_is_rejected_without_enough_budget(): void
    {
        $this->game->currentInvestment->update(['transfer_budget' => 1_000_00]);

        $result = app(PreseasonTourService::class)->organize($this->game, 'usa');

        $this->assertFalse($result['ok']);
        $this->assertNull($this->game->fresh()->preseason_tour);
    }

    public function test_tour_friendly_pays_more_than_a_normal_friendly(): void
    {
        $tourGame = $this->game;
        $normalGame = $this->makeGame();

        $this->assertTrue(app(PreseasonTourService::class)->organize($tourGame, 'mexico')['ok']);

        $tourMatch = $this->makePlayedHomeFriendly($tourGame);
        $normalMatch = $this->makePlayedHomeFriendly($normalGame);

        $listener = app(RecordMatchdayRevenue::class);
        $listener->handle(new MatchFinalized($tourMatch, $tourGame->fresh()));
        $listener->handle(new MatchFinalized($normalMatch, $normalGame->fresh()));

        $matchdayCategories = [
            FinancialTransaction::CATEGORY_MATCHDAY_TICKETS,
            FinancialTransaction::CATEGORY_MATCHDAY_SHIRTS,
            FinancialTransaction::CATEGORY_MATCHDAY_MERCH,
            FinancialTransaction::CATEGORY_MATCHDAY_BARS,
        ];

        $tourTotal = FinancialTransaction::where('game_id', $tourGame->id)
            ->whereIn('category', $matchdayCategories)
            ->sum('amount');
        $normalTotal = FinancialTransaction::where('game_id', $normalGame->id)
            ->whereIn('category', $matchdayCategories)
            ->sum('amount');

        $this->assertGreaterThan(0, $normalTotal);
        $this->assertGreaterThan($normalTotal, $tourTotal);

        // The tour books roughly double the gate (México ×2.0).
        $this->assertEqualsWithDelta($normalTotal * 2.0, $tourTotal, $normalTotal * 0.05);
    }

    public function test_family_derby_is_not_boosted_by_the_tour(): void
    {
        $this->assertTrue(app(PreseasonTourService::class)->organize($this->game, 'mexico')['ok']);

        $derby = $this->makePlayedHomeFriendly($this->game, PreseasonOpponentService::FAMILY_DERBY_ROUND_NUMBER);

        app(RecordMatchdayRevenue::class)->handle(new MatchFinalized($derby, $this->game->fresh()));

        // Tour matches use the "(gira)" line; the derby must not.
        $this->assertFalse(
            FinancialTransaction::where('game_id', $this->game->id)
                ->where('description', 'like', '%(gira)%')
                ->exists()
        );
    }
}
