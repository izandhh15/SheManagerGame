<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\PreSeasonFixtureProcessor;
use App\Modules\Season\Services\PreseasonTourService;
use App\Modules\Season\Services\TrainingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the QA medium bugs around preseason accounting:
 *
 * - M5: the club preseason stage charged the transfer budget without
 *   leaving a ledger entry (€60.000+ invisible). The charge is now booked
 *   as a FinancialTransaction with a stage-specific description.
 * - M27: the national-team stage never applied the "home" rate: the JS
 *   preview showed €75.000 while the server charged €125.000, because the
 *   raw country code ('ES') was compared against the destination name
 *   ('Spain').
 * - M28: the preseason tour was never reset between seasons: no new tour
 *   could be organized and the revenue multiplier leaked into every
 *   future preseason. PreSeasonFixtureProcessor now clears it.
 * - M29: the national stage was one-per-game while the club stage was
 *   one-per-season. Both are now one-per-season.
 *
 * Run:
 *   cd ~/workspace/virtua-fc-fem-medios && \
 *   DB_DATABASE=virtua_fc_medios_w3 ~/workspace/.tools/frankenphp php-cli \
 *     vendor/bin/phpunit tests/Feature/QaMediumFixes/
 */
class M5M27M28M29PreseasonAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Team $club;

    protected Game $game;

    protected TrainingStageService $stageService;

    protected PreseasonTourService $tourService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->club = Team::factory()->create(['name' => 'QA-M Elite WFC', 'country' => 'ES']);
        ClubProfile::create([
            'team_id' => $this->club->id,
            'reputation_level' => ClubProfile::REPUTATION_ELITE,
        ]);
        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->club->id,
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
            'game_id' => $this->game->id,
            'season' => '2026',
            'transfer_budget' => 10_000_000_00, // €10M in cents
            'scouting_tier' => 1,
        ]);

        GamePlayer::factory()->count(5)->create([
            'game_id' => $this->game->id,
            'team_id' => $this->club->id,
            'fitness' => 70,
            'morale' => 70,
        ]);

        $this->stageService = app(TrainingStageService::class);
        $this->tourService = app(PreseasonTourService::class);
    }

    /**
     * M5: the club stage charge must leave a ledger entry with a
     * stage-specific description (not the tour's), and the budget must
     * drop by exactly the charged amount.
     */
    public function test_club_stage_leaves_ledger_entry_with_stage_description(): void
    {
        // Spain / 1w / balanced / physical → home rate: 60_000 × 1.25 = 75_000.
        $result = $this->stageService->confirmClubStage($this->game, [
            'destination' => 'Spain',
            'duration' => '1w',
            'intensity' => 'balanced',
            'focus' => 'physical',
        ]);

        $this->assertTrue($result['ok'], 'Stage should confirm: '.($result['message'] ?? ''));
        $this->assertSame(75_000, $result['cost']);

        $transactions = FinancialTransaction::where('game_id', $this->game->id)
            ->where('category', FinancialTransaction::CATEGORY_TOUR)
            ->get();

        $this->assertCount(1, $transactions, 'The stage cost must be booked in the ledger');
        $this->assertSame(75_000_00, $transactions->first()->amount);
        $this->assertSame(FinancialTransaction::TYPE_EXPENSE, $transactions->first()->type);
        $this->assertStringContainsString('Stage de pretemporada', $transactions->first()->description);
        $this->assertStringNotContainsString('Gira de pretemporada', $transactions->first()->description);

        $this->assertSame(
            10_000_000_00 - 75_000_00,
            (int) $this->game->currentInvestment->fresh()->transfer_budget
        );
    }

    /**
     * M27: a national team staging at home (Spain) must be charged the
     * home rate (€75.000), matching the JS preview — not €125.000.
     */
    public function test_national_stage_applies_home_rate(): void
    {
        $nation = Team::factory()->create(['name' => 'QA-M Nation', 'country' => 'ES', 'type' => 'national']);
        $game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $nation->id,
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-07-01',
            'game_mode' => Game::MODE_TOURNAMENT,
            'federation_budget' => 5_000_000,
            'setup_completed_at' => now(),
            'needs_new_season_setup' => false,
            'needs_welcome' => false,
        ]);
        GamePlayer::factory()->count(5)->create([
            'game_id' => $game->id,
            'team_id' => $nation->id,
            'fitness' => 70,
            'morale' => 70,
        ]);

        $result = $this->stageService->confirmStage($game, [
            'destination' => 'Spain',
            'duration' => '1w',
            'intensity' => 'balanced',
            'focus' => 'physical',
        ]);

        $this->assertTrue($result['ok'], 'Stage should confirm: '.($result['message'] ?? ''));
        $this->assertSame(75_000, $result['cost'], 'Home rate must apply for Spain/ES');
        $this->assertSame(5_000_000 - 75_000, (int) $game->fresh()->federation_budget);
    }

    /**
     * M29: the national stage is one per SEASON (like the club stage),
     * not one per game.
     */
    public function test_national_stage_is_allowed_once_per_season(): void
    {
        $nation = Team::factory()->create(['name' => 'QA-M Nation B', 'country' => 'ES', 'type' => 'national']);
        $game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $nation->id,
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-07-01',
            'game_mode' => Game::MODE_TOURNAMENT,
            'federation_budget' => 5_000_000,
            'setup_completed_at' => now(),
            'needs_new_season_setup' => false,
            'needs_welcome' => false,
        ]);
        GamePlayer::factory()->count(5)->create([
            'game_id' => $game->id,
            'team_id' => $nation->id,
            'fitness' => 70,
            'morale' => 70,
        ]);

        $config = [
            'destination' => 'Spain',
            'duration' => '1w',
            'intensity' => 'balanced',
            'focus' => 'physical',
        ];

        $this->assertTrue($this->stageService->confirmStage($game, $config)['ok']);

        // Same season: still blocked.
        $repeat = $this->stageService->confirmStage($game->fresh(), $config);
        $this->assertFalse($repeat['ok']);
        $this->assertSame(__('game.stage_already_organized'), $repeat['message']);

        // Next season: allowed again.
        $game->update(['season' => '2027']);
        $nextSeason = $this->stageService->confirmStage($game->fresh(), $config);
        $this->assertTrue($nextSeason['ok'], 'Stage must be organizable again next season: '.($nextSeason['message'] ?? ''));
    }

    /**
     * M28: the season transition clears the preseason tour, so a new tour
     * can be organized next preseason and the old revenue multiplier dies.
     */
    public function test_preseason_tour_resets_on_season_transition(): void
    {
        $organized = $this->tourService->organize($this->game, 'mexico');
        $this->assertTrue($organized['ok'], 'Tour should organize: '.($organized['message'] ?? ''));
        $this->assertNotNull($this->game->fresh()->preseason_tour);

        app(PreSeasonFixtureProcessor::class)->process(
            $this->game->fresh(),
            new SeasonTransitionData('2026', '2027', 'ESP1')
        );

        $game = $this->game->fresh();
        $this->assertNull($game->preseason_tour, 'The tour must be cleared by the season transition');
        $this->assertTrue((bool) $game->preseason_opponents_pending);

        // A new tour can be organized for the new preseason.
        $again = $this->tourService->organize($game, 'usa');
        $this->assertTrue($again['ok'], 'A new tour should be organizable: '.($again['message'] ?? ''));
        $this->assertSame('usa', $again['destination']);
    }
}
