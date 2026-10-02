<?php

namespace Tests\Feature\Social;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\SocialMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The manager can fire back at hater posts with a predefined reply.
 * Replies apply small effects (board confidence, squad morale) and spark
 * fan reactions. One reply per post; positive posts can't be replied to.
 */
class HaterReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_to_hater_stores_reply_and_applies_effects(): void
    {
        [$game, $user, $post] = $this->buildScenario();

        $response = $this->actingAs($user)->post(
            route('game.social.reply', [$game->id, $post->id]),
            ['reply_key' => 'results_talk'],
        );

        $response->assertRedirect(route('game.social', $game->id));

        $post = $post->refresh();
        $this->assertSame('results_talk', $post->manager_reply_key);
        $this->assertNotEmpty($post->manager_reply_text);

        // Board confidence: 70 + 2.
        $this->assertSame(72, $game->refresh()->board_confidence);

        // Fan reactions to the comeback.
        $reactions = SocialPost::where('game_id', $game->id)
            ->where('context', 'manager_reply')
            ->count();
        $this->assertGreaterThanOrEqual(2, $reactions);
    }

    public function test_spicy_reply_hurts_board_confidence_and_morale(): void
    {
        [$game, $user, $post, $player] = $this->buildScenario(withPlayer: true);

        $this->actingAs($user)->post(
            route('game.social.reply', [$game->id, $post->id]),
            ['reply_key' => 'sofa_critic'],
        );

        // Board: 70 - 3. Morale: 80 - 2.
        $this->assertSame(67, $game->refresh()->board_confidence);
        $this->assertSame(
            78,
            GamePlayerMatchState::where('game_player_id', $player->id)->value('morale'),
        );
    }

    public function test_cannot_reply_twice_to_the_same_post(): void
    {
        [$game, $user, $post] = $this->buildScenario();

        $this->actingAs($user)->post(
            route('game.social.reply', [$game->id, $post->id]),
            ['reply_key' => 'results_talk'],
        );
        $this->actingAs($user)->post(
            route('game.social.reply', [$game->id, $post->id]),
            ['reply_key' => 'patience_project'],
        );

        // First reply stands.
        $this->assertSame('results_talk', $post->refresh()->manager_reply_key);
    }

    public function test_cannot_reply_to_positive_post(): void
    {
        [$game, $user, $post] = $this->buildScenario(sentiment: 1);

        $this->actingAs($user)->post(
            route('game.social.reply', [$game->id, $post->id]),
            ['reply_key' => 'results_talk'],
        );

        $this->assertNull($post->refresh()->manager_reply_key);
        $this->assertSame(70, $game->refresh()->board_confidence);
    }

    public function test_unknown_reply_key_is_rejected(): void
    {
        [$game, $user, $post] = $this->buildScenario();

        $this->actingAs($user)->post(
            route('game.social.reply', [$game->id, $post->id]),
            ['reply_key' => 'nope'],
        )->assertSessionHasErrors('reply_key');

        $this->assertNull($post->refresh()->manager_reply_key);
    }

    public function test_other_users_cannot_reply(): void
    {
        [$game, , $post] = $this->buildScenario();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(
            route('game.social.reply', [$game->id, $post->id]),
            ['reply_key' => 'results_talk'],
        )->assertForbidden();

        $this->assertNull($post->refresh()->manager_reply_key);
    }

    public function test_reply_options_are_localized(): void
    {
        $options = app(SocialMediaService::class)->haterReplyOptions();

        $this->assertCount(4, $options);
        foreach ($options as $option) {
            $this->assertArrayHasKey('key', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertNotEmpty($option['label']);
        }
        $this->assertSame(
            array_keys(SocialMediaService::HATER_REPLIES),
            array_column($options, 'key'),
        );
    }

    /**
     * @return array{Game, User, SocialPost, ?GamePlayer}
     */
    private function buildScenario(int $sentiment = -1, bool $withPlayer = false): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'board_confidence' => 70,
        ]);

        $post = SocialPost::create([
            'game_id' => $game->id,
            'author_name' => 'Hater Fan',
            'author_handle' => '@hater_01',
            'text' => 'Este tío no tiene ni idea de fútbol.',
            'sentiment' => $sentiment,
            'likes' => 100,
            'context' => 'post_match',
        ]);

        $player = null;
        if ($withPlayer) {
            $player = GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $team->id,
            ]);
            // The factory seeds match state with random morale; pin it.
            GamePlayerMatchState::where('game_player_id', $player->id)->update(['morale' => 80]);
        }

        return [$game, $user, $post, $player];
    }
}
