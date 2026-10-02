<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameJournalist;
use App\Models\GameMatch;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\JournalistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FEATURE 9/9 — Journalists on the fake social network.
 *
 * Fictional reporter accounts (invented names, no real people or brands)
 * are seeded with each game and publish news tweets about the game's
 * actuality: signings, results, board rumours. The manager interacts via
 * the existing social actions (likes; hater replies are fan-only).
 */
class JournalistAccountsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Real media brands and outlets must never appear as journalists.
     */
    private const BANNED_FRAGMENTS = [
        'marca', 'diario as', 'sport', 'mundo deportivo', 'superdeporte',
        'relevo', 'okdiario', 'el desmarque', 'fichajes.net',
    ];

    public function test_seed_creates_the_full_invented_newsroom(): void
    {
        $game = $this->makeGame();

        $journalists = app(JournalistService::class)->seedFor($game);

        $this->assertCount(count(JournalistService::ROSTER), $journalists);
        $this->assertSame($game->id, $journalists->first()->game_id);

        // Handles are unique per game.
        $handles = $journalists->pluck('handle');
        $this->assertSame($handles->count(), $handles->unique()->count());

        // Every handle looks like a personal account, not a brand.
        foreach ($handles as $handle) {
            $this->assertStringStartsWith('@', $handle);
        }
    }

    public function test_seed_is_idempotent(): void
    {
        $game = $this->makeGame();
        $service = app(JournalistService::class);

        $service->seedFor($game);
        $service->seedFor($game);

        $this->assertSame(
            count(JournalistService::ROSTER),
            GameJournalist::where('game_id', $game->id)->count(),
        );
    }

    public function test_no_journalist_matches_a_real_media_brand(): void
    {
        $game = $this->makeGame();
        $journalists = app(JournalistService::class)->seedFor($game);

        foreach ($journalists as $journalist) {
            $haystack = strtolower($journalist->name.' '.$journalist->handle.' '.$journalist->specialty);
            foreach (self::BANNED_FRAGMENTS as $banned) {
                $this->assertStringNotContainsString(
                    $banned,
                    $haystack,
                    "Journalist '{$journalist->name}' looks like a real brand.",
                );
            }
        }
    }

    public function test_welcome_posts_introduce_the_newsroom(): void
    {
        $game = $this->makeGame();
        $service = app(JournalistService::class);
        $service->seedFor($game);

        $service->publishWelcome($game);

        $posts = SocialPost::where('game_id', $game->id)
            ->where('context', 'journalist_welcome')
            ->get();

        $this->assertGreaterThanOrEqual(1, $posts->count());
        foreach ($posts as $post) {
            $this->assertNotNull($post->journalist_id);
            $this->assertSame(0, $post->sentiment);
            $this->assertNotEmpty($post->text);
        }
    }

    public function test_match_report_is_published_for_the_user_team(): void
    {
        [$game, $home, $away] = $this->makeGameWithMatch();

        $match = new GameMatch([
            'game_id' => $game->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $post = app(JournalistService::class)->postMatchReport($game, $match);

        $this->assertNotNull($post);
        $this->assertSame('journalist_match', $post->context);
        $this->assertNotNull($post->journalist_id);
        $this->assertSame('@marco_tellez', $post->author_handle);
        $this->assertStringContainsString('2-1', $post->text);
        $this->assertStringContainsString($home->name, $post->text);
        $this->assertStringContainsString($away->name, $post->text);
    }

    public function test_no_match_report_for_ai_vs_ai_matches(): void
    {
        [$game] = $this->makeGameWithMatch();

        $strangers = [Team::factory()->create(), Team::factory()->create()];
        $match = new GameMatch([
            'game_id' => $game->id,
            'home_team_id' => $strangers[0]->id,
            'away_team_id' => $strangers[1]->id,
            'home_score' => 0,
            'away_score' => 0,
        ]);

        $post = app(JournalistService::class)->postMatchReport($game, $match);

        $this->assertNull($post);
        $this->assertSame(0, SocialPost::where('game_id', $game->id)->count());
    }

    public function test_publishing_is_a_noop_without_seeded_journalists(): void
    {
        [$game, $home, $away] = $this->makeGameWithMatch(seedJournalists: false);

        $match = new GameMatch([
            'game_id' => $game->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => 1,
            'away_score' => 1,
        ]);

        $service = app(JournalistService::class);

        $this->assertNull($service->postMatchReport($game, $match));
        $this->assertNull($service->postTransferNews($game, 'Ada Hegerberg', 'OL Lyonnes', 'FC Barcelona', 'in'));
        $this->assertNull($service->postBoardRumor($game, 'FC Barcelona'));
        $this->assertNull($service->pick($game, 'fichajes'));

        $this->assertSame(0, SocialPost::where('game_id', $game->id)->count());
    }

    public function test_transfer_news_announces_signings_and_sales(): void
    {
        $game = $this->makeGame();
        app(JournalistService::class)->seedFor($game);

        $incoming = app(JournalistService::class)->postTransferNews(
            $game, 'Clara Méndez', 'Atlético de Madrid', $game->team->name, 'in',
        );

        $this->assertNotNull($incoming);
        $this->assertSame('journalist_transfer', $incoming->context);
        $this->assertSame('@luciaferran', $incoming->author_handle);
        $this->assertStringContainsString('Clara Méndez', $incoming->text);

        $outgoing = app(JournalistService::class)->postTransferNews(
            $game, 'Júlia Casas', $game->team->name, 'Chelsea FCW', 'out',
        );

        $this->assertStringContainsString('Júlia Casas', $outgoing->text);
        $this->assertStringContainsString('Chelsea FCW', $outgoing->text);
    }

    public function test_board_rumor_uses_the_rumours_desk(): void
    {
        $game = $this->makeGame();
        app(JournalistService::class)->seedFor($game);

        $post = app(JournalistService::class)->postBoardRumor($game, $game->team->name);

        $this->assertNotNull($post);
        $this->assertSame('journalist_rumor', $post->context);
        $this->assertSame('@paularincon', $post->author_handle);
        $this->assertStringContainsString($game->team->name, $post->text);
    }

    public function test_manager_can_like_a_journalist_post_once(): void
    {
        $game = $this->makeGame();
        $user = $game->user;
        app(JournalistService::class)->seedFor($game);
        app(JournalistService::class)->publishWelcome($game);

        $post = SocialPost::where('game_id', $game->id)->firstOrFail();
        $this->assertNotNull($post->journalist_id);
        $initial = $post->likes;

        $this->actingAs($user)
            ->post(route('game.social.like', [$game->id, $post->id]))
            ->assertRedirect(route('game.social', $game->id));

        $this->assertSame($initial + 1, $post->refresh()->likes);
    }

    public function test_like_increments_once_per_session(): void
    {
        $game = $this->makeGame();
        $user = $game->user;
        app(JournalistService::class)->seedFor($game);
        app(JournalistService::class)->publishWelcome($game);

        $post = SocialPost::where('game_id', $game->id)->firstOrFail();
        $initial = $post->likes;

        $url = route('game.social.like', [$game->id, $post->id]);
        $this->actingAs($user)->post($url);
        $this->actingAs($user)->post($url); // second click: no double count

        $this->assertSame($initial + 1, $post->refresh()->likes);
    }

    public function test_other_users_cannot_like_posts(): void
    {
        $game = $this->makeGame();
        $intruder = User::factory()->create();
        app(JournalistService::class)->seedFor($game);
        app(JournalistService::class)->publishWelcome($game);

        $post = SocialPost::where('game_id', $game->id)->firstOrFail();

        $this->actingAs($intruder)
            ->post(route('game.social.like', [$game->id, $post->id]))
            ->assertForbidden();
    }

    public function test_journalist_posts_appear_in_the_social_feed(): void
    {
        $game = $this->makeGame();
        $user = $game->user;
        app(JournalistService::class)->seedFor($game);
        app(JournalistService::class)->publishWelcome($game);

        $response = $this->actingAs($user)->get(route('game.social', $game->id));

        $response->assertOk();
        $response->assertSee('Lucía Ferrán');
        $response->assertSee('@luciaferran');
        $response->assertSee('Periodista');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeGame(): Game
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES', 'name' => 'Valencia CF']);

        return Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'board_confidence' => 70,
        ]);
    }

    /**
     * @return array{Game, Team, Team}
     */
    private function makeGameWithMatch(bool $seedJournalists = true): array
    {
        $game = $this->makeGame();
        $home = $game->team;
        $away = Team::factory()->create(['country' => 'ES', 'name' => 'FC Levante Badalona']);

        if ($seedJournalists) {
            app(JournalistService::class)->seedFor($game);
        }

        return [$game, $home, $away];
    }
}
