<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\GameStadium;
use App\Models\ManagerStats;
use App\Models\SeasonArchive;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\User;
use App\Modules\Government\Services\GovernmentVenueService;
use App\Modules\Match\Jobs\ProcessCareerActions;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SeasonArchiveProcessor;
use App\Modules\Stadium\Services\FanLoyaltyService;
use App\Modules\Stadium\Services\GameStadiumResolver;
use App\Modules\Stadium\Services\NamingOfferFactory;
use App\Modules\Stadium\Services\NamingRightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Baja fase 5 — transacción / idempotencia / concurrencia (TRIAGE-BAJA familia 5).
 */
class ReviewBajaTransactionsTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    // ── GovernmentVenueService::accept() ────────────────────────────────

    private function venueOffer(Game $game, GameMatch $match): GameNotification
    {
        return GameNotification::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'type' => GameNotification::TYPE_GOVERNMENT_VENUE_OFFER,
            'title' => 'Test',
            'priority' => GameNotification::PRIORITY_INFO,
            'metadata' => [
                'government' => 'Generalitat Valenciana',
                'stadiums' => ['Castalia', 'José Rico Pérez'],
                'match_id' => $match->id,
                'status' => 'pending',
            ],
            'game_date' => $game->current_date,
        ]);
    }

    public function test_government_venue_second_accept_is_rejected(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'España', 'type' => 'national', 'country' => 'ES']);
        $opp = Team::factory()->create(['name' => 'Francia', 'type' => 'national', 'country' => 'FR']);
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'season' => '2026',
            'current_date' => '2026-09-01',
        ]);
        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $team->id,
            'away_team_id' => $opp->id,
            'scheduled_date' => '2026-10-01 18:00:00',
            'played' => false,
        ]);

        $service = app(GovernmentVenueService::class);
        $notification = $this->venueOffer($game, $match);

        $first = $service->accept($notification, 'Castalia');
        $this->assertTrue($first['ok']);

        // A second accept — even with the now-stale model instance — must be
        // rejected: the row is re-read under lock inside the transaction.
        $second = $service->accept($notification, 'José Rico Pérez');
        $this->assertFalse($second['ok']);
        $this->assertSame('game.gov_venue_already_answered', $second['error']);

        $this->assertSame('Castalia', $match->fresh()->neutral_venue_name);
    }

    // ── ManagerStats::recordResult() ────────────────────────────────────

    public function test_manager_stats_record_result_is_atomic_and_synced(): void
    {
        $user = User::factory()->create();
        $stats = ManagerStats::create(['user_id' => $user->id]);

        $stats->recordResult('win');
        $stats->recordResult('win');
        $stats->recordResult('draw');
        $stats->recordResult('loss');
        $stats->recordResult('win');

        // In-memory instance is synced (no stale read after the lock).
        $this->assertSame(5, $stats->matches_played);
        $this->assertSame(3, $stats->matches_won);
        $this->assertSame(1, $stats->matches_drawn);
        $this->assertSame(1, $stats->matches_lost);
        $this->assertEquals(60.0, $stats->win_percentage);
        $this->assertSame(1, $stats->current_unbeaten_streak);
        $this->assertSame(3, $stats->longest_unbeaten_streak);

        // DB agrees.
        $fresh = $stats->fresh();
        $this->assertSame(5, $fresh->matches_played);
        $this->assertSame(3, $fresh->matches_won);
    }

    // ── SeasonArchiveProcessor idempotent path ──────────────────────────

    public function test_season_archive_idempotent_run_purges_match_events(): void
    {
        $team = Team::factory()->create();
        $opp = Team::factory()->create();
        $game = Game::factory()->forTeam($team)->create(['season' => '2026']);
        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $team->id,
            'away_team_id' => $opp->id,
            'played' => true,
        ]);
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
        ]);

        SeasonArchive::create([
            'game_id' => $game->id,
            'season' => '2026',
            'final_standings' => [],
            'player_season_stats' => [],
            'season_awards' => ['mvp' => 'x'],
            'match_results' => [],
            'match_events_archive' => [],
        ]);

        DB::table('match_events')->insert([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'game_player_id' => $player->id,
            'team_id' => $team->id,
            'minute' => 23,
            'event_type' => 'goal',
            'phase' => 'first_half',
        ]);

        $processor = app(SeasonArchiveProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2026', newSeason: '2027', competitionId: 'ESP1');
        $processor->process($game, $data);

        // Idempotent re-run: archive kept, metadata repopulated, and the
        // live match_events purged so they can't contaminate next season.
        $this->assertSame(0, DB::table('match_events')->where('game_id', $game->id)->count());
        $this->assertSame(['mvp' => 'x'], $data->getMetadata('seasonAwards'));
        $this->assertSame(1, SeasonArchive::where('game_id', $game->id)->where('season', '2026')->count());
    }

    // ── ProcessCareerActions flag ownership ─────────────────────────────

    private function invokeClearOwnFlag(ProcessCareerActions $job): void
    {
        $ref = new ReflectionMethod(ProcessCareerActions::class, 'clearOwnFlag');
        $ref->setAccessible(true);
        $ref->invoke($job);
    }

    public function test_career_actions_job_clears_only_its_own_flag(): void
    {
        $game = Game::factory()->create();
        $game->update(['career_actions_processing_at' => '2026-01-01 00:00:00']);

        // A job holding a DIFFERENT flag timestamp must not wipe this one.
        $this->invokeClearOwnFlag(new ProcessCareerActions($game->id, 1, '2026-05-05 00:00:00'));
        $this->assertSame(
            '2026-01-01 00:00:00',
            $game->fresh()->career_actions_processing_at->toDateTimeString()
        );

        // The job that set the flag clears it.
        $this->invokeClearOwnFlag(new ProcessCareerActions($game->id, 1, '2026-01-01 00:00:00'));
        $this->assertNull($game->fresh()->career_actions_processing_at);
    }

    // ── PostInternetMessage daily limit ─────────────────────────────────

    private function internetGame(User $user): Game
    {
        return Game::factory()->create(['user_id' => $user->id]);
    }

    private function seedManagerPosts(Game $game, int $count): void
    {
        foreach (range(1, $count) as $i) {
            SocialPost::create([
                'game_id' => $game->id,
                'author_name' => 'Míster',
                'author_handle' => '@mister',
                'text' => "post {$i}",
                'context' => 'manager_post',
            ]);
        }
    }

    public function test_internet_post_at_limit_is_rejected(): void
    {
        $user = User::factory()->create();
        $game = $this->internetGame($user);
        $this->actingAs($user);
        $this->seedManagerPosts($game, 5);

        $response = $this->post(
            route('game.internet.post', ['gameId' => $game->id]),
            ['text' => 'one too many']
        );

        $response->assertSessionHas('error');
        $this->assertSame(
            5,
            SocialPost::where('game_id', $game->id)->where('context', 'manager_post')->count()
        );
    }

    public function test_internet_post_below_limit_succeeds(): void
    {
        $user = User::factory()->create();
        $game = $this->internetGame($user);
        $this->actingAs($user);
        $this->seedManagerPosts($game, 4);

        $response = $this->post(
            route('game.internet.post', ['gameId' => $game->id]),
            ['text' => 'hala madrid']
        );

        $response->assertSessionHas('success');
        $this->assertSame(
            5,
            SocialPost::where('game_id', $game->id)->where('context', 'manager_post')->count()
        );
    }

    // ── NamingRightsService::seekSponsors() ─────────────────────────────

    private function preSeasonGame(Team $team): Game
    {
        return Game::factory()->forTeam($team)->create(['pre_season' => true, 'season' => '2026']);
    }

    public function test_seek_sponsors_does_not_charge_fee_when_minting_fails(): void
    {
        $team = Team::factory()->create(['stadium_seats' => 10_000]);
        $game = $this->preSeasonGame($team);

        GameStadium::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'base_capacity' => 10_000,
        ]);
        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'reputation_level' => 'established',
            'base_reputation_level' => 'established',
            'reputation_points' => 250,
            'base_loyalty' => 90,
            'loyalty_points' => 90,
        ]);
        $investment = GameInvestment::create([
            'game_id' => $game->id,
            'season' => $game->season,
            'transfer_budget' => 10_000_00,
        ]);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('create')->byDefault();

        $factory = Mockery::mock(NamingOfferFactory::class);
        $factory->shouldReceive('createOffer')->andThrow(new \RuntimeException('mint boom'));

        $service = new NamingRightsService(
            new GameStadiumResolver(),
            new FanLoyaltyService(),
            $notifications,
            $factory,
        );

        $budgetBefore = $investment->fresh()->transfer_budget;

        try {
            $service->seekSponsors($game);
            $this->fail('seekSponsors should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('mint boom', $e->getMessage());
        }

        // The whole search rolled back: no fee charged, no cooldown stamped.
        $this->assertSame($budgetBefore, $investment->fresh()->transfer_budget);
        $this->assertNull(
            GameStadium::where('game_id', $game->id)->value('naming_rights_last_sought_date')
        );
        $this->assertSame(
            0,
            DB::table('financial_transactions')->where('game_id', $game->id)->count()
        );
    }
}
