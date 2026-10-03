<?php

namespace Tests\Feature\QaCriticalFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\LeaderboardStatsProcessor;
use App\Modules\Season\Services\SeasonClosingPipeline;
use App\Modules\Season\Services\SeasonSetupPipeline;
use App\Modules\Season\Services\SeasonTransitionChunkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C3 regression: the stuck-transition recovery in ShowGame and
 * GameSetupStatus ran a chunk and then UNCONDITIONALLY refreshed
 * season_transitioning_at — resurrecting the flag when the chunk had
 * just COMPLETED the transition. The next poll then re-ran the whole
 * closing pipeline on the new season, skipping a year unplayed.
 *
 * Fix (commit 8583c98): both views capture
 *   $chunkResult = runChunk(...)
 * and only re-arm the flag when !($chunkResult['done'] ?? false).
 *
 * Ported from the QA reproduction
 * (qa/workers/agent-23/SeasonTransitionChunkQaTest.php ::
 * test_stuck_recovery_does_not_resurrect_flag_after_completion).
 * The A21 probes from that file are intentionally NOT ported (different
 * bug, still unfixed, out of scope).
 *
 * Run:
 *   DB_USERNAME=virtua_fc DB_PASSWORD=virtua_fc DB_DATABASE=virtua_fc_fix_c3 \
 *     ~/workspace/.tools/frankenphp php-cli vendor/bin/phpunit \
 *     tests/Feature/QaCriticalFixes/C3SeasonTransitionFlagTest.php
 */
class C3SeasonTransitionFlagTest extends TestCase
{
    use RefreshDatabase;

    protected Game $game;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = $this->makeFinishedSeasonGame();
    }

    // ------------------------------------------------------------------
    // Fixture: a game whose 2026 season is fully played.
    // ------------------------------------------------------------------

    protected function makeFinishedSeasonGame(): Game
    {
        // Full ES pyramid so PromotionRelegationProcessor's snapshot
        // builder sees complete standings (it refuses partial tiers).
        $tiers = [
            'ESP1' => 16,
            'ESP2' => 14,
            'ESP3A' => 14,
            'ESP3B' => 14,
            'ESP3C' => 14,
        ];
        foreach ($tiers as $cid => $count) {
            Competition::factory()->league()->create([
                'id' => $cid,
                'name' => "QA {$cid}",
                'tier' => 1,
                'season' => '2026',
            ]);
        }

        $this->user = User::factory()->create();
        $userTeam = Team::factory()->create();

        $game = Game::factory()->forTeam($userTeam)->create([
            'user_id' => $this->user->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2026',
            'base_season' => '2026',
            'current_date' => '2027-06-15',
            'needs_welcome' => false,
        ]);

        foreach ($tiers as $cid => $count) {
            $teams = [];
            for ($i = 0; $i < $count; $i++) {
                // User's team plays in ESP1, mid-table (position 8).
                if ($cid === 'ESP1' && $i === 7) {
                    $teams[] = $userTeam;
                    continue;
                }
                $teams[] = Team::factory()->create();
            }

            foreach ($teams as $i => $t) {
                CompetitionEntry::create([
                    'game_id' => $game->id,
                    'competition_id' => $cid,
                    'team_id' => $t->id,
                    'entry_round' => 1,
                ]);
                $won = max(0, 20 - $i);
                $drawn = 4;
                $lost = 26 - $won - $drawn;
                GameStanding::create([
                    'game_id' => $game->id,
                    'competition_id' => $cid,
                    'team_id' => $t->id,
                    'position' => $i + 1,
                    'played' => 26,
                    'won' => $won,
                    'drawn' => $drawn,
                    'lost' => $lost,
                    'goals_for' => 40 - $i,
                    'goals_against' => 10 + $i,
                    'points' => 3 * $won + $drawn,
                ]);
            }
        }

        // User squad: one expiring contract, four long contracts.
        GamePlayer::factory()->forGame($game)->forTeam($userTeam)->create([
            'contract_until' => '2027-06-30',
        ]);
        GamePlayer::factory()->forGame($game)->forTeam($userTeam)->count(4)->create([
            'contract_until' => '2029-06-30',
        ]);

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => '2026',
            'transfer_budget' => 1_000_000_00,
            'scouting_tier' => 1,
        ]);
        GameFinances::create([
            'game_id' => $game->id,
            'season' => '2026',
            'projected_revenue' => 5_000_000_00,
            'projected_wages' => 2_000_000_00,
            'projected_position' => 8,
        ]);

        // Season fully played (StartNewSeason's guard would pass).
        $esp1Teams = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', 'ESP1')
            ->limit(2)
            ->pluck('team_id');
        GameMatch::factory()->forGame($game)->create([
            'competition_id' => 'ESP1',
            'home_team_id' => $esp1Teams[0],
            'away_team_id' => $esp1Teams[1],
            'played' => true,
            'home_score' => 2,
            'away_score' => 1,
            'scheduled_date' => '2027-05-20',
        ]);

        return $game;
    }

    protected function startTransition(Game $game): void
    {
        // Mirrors StartNewSeason's atomic check-and-set.
        Game::where('id', $game->id)
            ->whereNull('season_transitioning_at')
            ->update(['season_transitioning_at' => now()]);
    }

    protected function chunkService(): SeasonTransitionChunkService
    {
        return app(SeasonTransitionChunkService::class);
    }

    /**
     * Set up a nearly-complete transition whose flag has gone stale
     * (> 2 min), so the next visit takes the stuck-recovery branch and
     * its chunk finishes the transition.
     *
     * Deterministic hold: LeaderboardStatsProcessor is swapped for a
     * double that always throws, so the checkpoint can never overshoot
     * past it (and the transition can never complete) during setup.
     * The double is unbound before the actual recovery runs.
     */
    protected function makeStaleNearlyCompleteTransition(): void
    {
        $closing = app(SeasonClosingPipeline::class)->getProcessors();
        $targetIndex = null;
        foreach ($closing as $i => $p) {
            if ($p instanceof LeaderboardStatsProcessor) {
                $targetIndex = $i;
                break;
            }
        }
        $this->assertNotNull($targetIndex, 'LeaderboardStatsProcessor not in closing pipeline');

        $this->app->bind(LeaderboardStatsProcessor::class, fn () => new class extends LeaderboardStatsProcessor {
            public function __construct() {}

            public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
            {
                throw new \RuntimeException('C3 hold: pause before LeaderboardStatsProcessor');
            }
        });

        $this->startTransition($this->game);

        for ($i = 0; $i < 200; $i++) {
            $step = Game::find($this->game->id)->season_transition_step;
            if ($step !== null && $step >= $targetIndex - 1) {
                break;
            }
            try {
                $this->chunkService()->runChunk(Game::find($this->game->id), 30.0);
            } catch (\RuntimeException $e) {
                if ($e->getMessage() !== 'C3 hold: pause before LeaderboardStatsProcessor') {
                    throw $e;
                }
            }
        }

        $held = Game::find($this->game->id);
        $this->assertSame($targetIndex - 1, $held->season_transition_step, 'transition did not hold before LeaderboardStatsProcessor');
        $this->assertNotNull($held->season_transitioning_at, 'transition flag must stay set so recovery can run');

        // Restore the real processor for the recovery under test.
        $this->app->bind(LeaderboardStatsProcessor::class, LeaderboardStatsProcessor::class);

        Game::where('id', $this->game->id)->update([
            'setup_completed_at' => now(),
            'needs_new_season_setup' => false,
            'season_transitioning_at' => now()->subMinutes(5),
        ]);
    }

    // ------------------------------------------------------------------
    // C3: ShowGame's stuck-recovery must not resurrect the flag when the
    // recovery chunk COMPLETES the transition.
    // ------------------------------------------------------------------

    public function test_showgame_stuck_recovery_does_not_resurrect_flag_after_completion(): void
    {
        $this->makeStaleNearlyCompleteTransition();

        $response = $this->actingAs($this->user)->get("/game/{$this->game->id}");
        $response->assertOk(); // game-loading view (ShowGame catches Throwable -> dashboard redirect on error)

        $game = Game::find($this->game->id);
        $this->assertSame('2027', $game->season, 'recovery chunk did not finish the transition');
        $this->assertNull(
            $game->season_transitioning_at,
            'C3 REGRESSION: ShowGame re-set season_transitioning_at after the chunk completed the transition — ' .
            'the next advance poll would re-run the closing pipeline on the new season'
        );
        $this->assertNull($game->season_transition_step);
        $this->assertFalse($game->isTransitioningSeason());

        // The flag was truly cleared, not left in a half state: a new
        // transition must still be startable exactly once.
        $this->startTransition($game);
        $game->refresh();
        $this->assertNotNull($game->season_transitioning_at);
    }

    // ------------------------------------------------------------------
    // C3: GameSetupStatus (the polling endpoint used while the loading
    // screen shows) has the same recovery path and must not resurrect
    // the flag either.
    // ------------------------------------------------------------------

    public function test_game_setup_status_recovery_does_not_resurrect_flag_after_completion(): void
    {
        $this->makeStaleNearlyCompleteTransition();

        $response = $this->actingAs($this->user)->getJson("/game/{$this->game->id}/setup-status");
        $response->assertOk();

        $game = Game::find($this->game->id);
        $this->assertSame('2027', $game->season, 'recovery chunk did not finish the transition');
        $this->assertNull(
            $game->season_transitioning_at,
            'C3 REGRESSION: GameSetupStatus re-set season_transitioning_at after the chunk completed the transition — ' .
            'the next advance poll would re-run the closing pipeline on the new season'
        );
        $this->assertNull($game->season_transition_step);
        $this->assertFalse($game->isTransitioningSeason());
    }
}
