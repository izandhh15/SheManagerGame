<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\PlayerSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A13: a "lifestyle" post from a player with no name must not 500 the
 * advance flow (author_name is NOT NULL on social_posts).
 * The fix skips the post silently when there is no valid author name;
 * the normal flow with a named player must keep publishing.
 */
class QaA13LifestyleNullNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifestyle_with_null_player_name_never_inserts_broken_row(): void
    {
        [$game] = $this->buildScenario(playerName: null);

        $service = new PlayerSocialService();

        // Simulate many advances: the 12% random gate makes a single call
        // flaky, so loop until we're sure the selection path was exercised
        // many times (0.88^300 chance of never reaching it).
        $results = [];
        for ($i = 0; $i < 300; $i++) {
            $results[] = $service->maybePostLifestyle($game);
        }

        // No exception thrown (would fail the test), no post created,
        // and no row with a NULL/empty author_name anywhere.
        foreach ($results as $result) {
            $this->assertNull($result);
        }
        $this->assertSame(0, SocialPost::where('game_id', $game->id)->count());
        $this->assertSame(0, SocialPost::whereNull('author_name')->count());
    }

    public function test_lifestyle_with_empty_player_name_skips_too(): void
    {
        [$game] = $this->buildScenario(playerName: '');

        $service = new PlayerSocialService();
        for ($i = 0; $i < 300; $i++) {
            $this->assertNull($service->maybePostLifestyle($game));
        }

        $this->assertSame(0, SocialPost::where('game_id', $game->id)->count());
    }

    public function test_lifestyle_with_named_player_still_publishes(): void
    {
        [$game] = $this->buildScenario(playerName: 'Mariana López');

        $service = new PlayerSocialService();

        $post = null;
        for ($i = 0; $i < 300 && $post === null; $i++) {
            $post = $service->maybePostLifestyle($game);
        }

        $this->assertNotNull($post, 'Expected a lifestyle post to be published after 300 attempts');
        $this->assertSame('Mariana López', $post->author_name);
        $this->assertSame('player_lifestyle', $post->context);
        $this->assertNotEmpty($post->author_handle);
        $this->assertNotEmpty($post->text);
    }

    public function test_mixed_squad_never_produces_null_author_rows(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

        GamePlayer::factory()->create(['game_id' => $game->id, 'team_id' => $team->id, 'name' => null]);
        GamePlayer::factory()->create(['game_id' => $game->id, 'team_id' => $team->id, 'name' => 'Mariana López']);
        GamePlayer::factory()->create(['game_id' => $game->id, 'team_id' => $team->id, 'name' => 'Ana Ruiz']);

        $service = new PlayerSocialService();
        for ($i = 0; $i < 300; $i++) {
            $service->maybePostLifestyle($game);
        }

        $this->assertSame(0, SocialPost::whereNull('author_name')->count());
        $this->assertSame(0, SocialPost::where('author_name', '')->count());
    }

    /**
     * @return array{0: Game}
     */
    private function buildScenario(?string $playerName): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => $playerName,
        ]);

        return [$game];
    }
}
