<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\ClubSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The club's official social media: announcements spawn the official post
 * plus fan replies, exciting signings build hype, and duplicates are
 * rejected.
 */
class ClubSocialServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Local pgsql test DB (see phpunit.xml); independent of TestCase's override. */
    protected $connectionsToTransact = ['pgsql'];

    private User $user;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Competition::factory()->league()->create(['id' => 'ESP3', 'country' => 'ES', 'tier' => 3]);

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['name' => 'CD Getafe Femenino', 'country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP3',
            'country' => 'ES',
            'season' => '2026',
            'social_hype' => 0,
        ]);

        ClubProfile::create([
            'team_id' => $this->team->id,
            'reputation_level' => ClubProfile::REPUTATION_MODEST,
        ]);

        // Squad of average players (overall ~70).
        for ($i = 0; $i < 5; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->team->id,
                'overall_score' => 70,
            ]);
        }
    }

    private function starPlayer(): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'name' => 'Estrella Fichaje',
            'overall_score' => 88,
        ]);
    }

    public function test_signing_announcement_creates_post_replies_and_hype(): void
    {
        $player = $this->starPlayer();

        $result = app(ClubSocialService::class)->announce($this->game, 'signing', $player->id);

        $this->assertTrue($result['ok']);

        $post = SocialPost::find($result['post_id']);
        $this->assertNotNull($post);
        $this->assertSame('club_official', $post->context);
        $this->assertStringContainsString('Estrella Fichaje', $post->text);
        $this->assertStringContainsString('@getafe_oficial', $post->author_handle);

        $replies = SocialPost::where('parent_post_id', $post->id)->get();
        $this->assertGreaterThanOrEqual(4, $replies->count());
        $this->assertTrue($replies->every(fn ($r) => $r->context === 'club_official_reply'));

        // An 88-rated signing into a 70-average squad is exciting: hype up.
        $this->assertGreaterThan(0, (int) $this->game->fresh()->social_hype);

        // Big signing: most replies positive.
        $positives = $replies->where('sentiment', 1)->count();
        $this->assertGreaterThan($replies->count() / 2, $positives);
    }

    public function test_duplicate_announcement_is_rejected(): void
    {
        $player = $this->starPlayer();
        $service = app(ClubSocialService::class);

        $this->assertTrue($service->announce($this->game, 'signing', $player->id)['ok']);

        $again = $service->announce($this->game, 'signing', $player->id);
        $this->assertFalse($again['ok']);
        $this->assertSame(__('game.club_social_already_announced'), $again['message']);
    }

    public function test_selling_a_star_angers_the_fans(): void
    {
        $player = $this->starPlayer();

        $result = app(ClubSocialService::class)
            ->announce($this->game, 'sale', $player->id, ['destination' => 'FC Barcelona']);

        $this->assertTrue($result['ok']);

        $replies = SocialPost::where('parent_post_id', $result['post_id'])->get();
        $negatives = $replies->where('sentiment', -1)->count();
        $this->assertGreaterThan($replies->count() / 2, $negatives);
    }

    public function test_injury_report_draws_supportive_replies(): void
    {
        mt_srand(20261002);
        $player = $this->starPlayer();

        $result = app(ClubSocialService::class)
            ->announce($this->game, 'injury', $player->id, ['weeks' => 6]);

        $this->assertTrue($result['ok']);
        $post = SocialPost::find($result['post_id']);
        $this->assertStringContainsString('6', $post->text);

        $replies = SocialPost::where('parent_post_id', $result['post_id'])->get();
        $this->assertGreaterThanOrEqual(4, $replies->count());

        // Supportive replies exist and use the encouragement templates.
        $supportive = $replies->where('sentiment', 1);
        $this->assertNotEmpty($supportive);
        $this->assertTrue(
            $supportive->contains(fn ($r) => str_contains($r->text, 'ánimo') || str_contains($r->text, 'Get well')),
        );
    }

    public function test_season_ticket_campaign_can_be_announced_once(): void
    {
        $service = app(ClubSocialService::class);

        $this->assertTrue($service->announce($this->game, 'season_tickets')['ok']);
        $this->assertFalse($service->announce($this->game, 'season_tickets')['ok']);
    }

    public function test_hype_boosts_home_attendance_projection(): void
    {
        $attendance = app(\App\Modules\Stadium\Services\MatchAttendanceService::class);

        $match = new \App\Models\GameMatch([
            'home_team_id' => $this->team->id,
            'away_team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'competition_id' => 'ESP3',
        ]);

        $this->game->social_hype = 0;
        $base = $attendance->describeForMatch($match, $this->game);

        $this->game->social_hype = 100;
        $hyped = $attendance->describeForMatch($match, $this->game);

        $this->assertNotNull($base);
        $this->assertNotNull($hyped);
        // +40% at full hype.
        $this->assertEqualsWithDelta($base['attendance'] * 1.4, $hyped['attendance'], $base['attendance'] * 0.05 + 1);
    }
}
