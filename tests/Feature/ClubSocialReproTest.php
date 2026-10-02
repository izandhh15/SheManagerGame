<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubSocialReproTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_social_valencia_renders_200(): void
    {
        \App\Models\Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Valencia CF Femenino', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2026',
            'social_hype' => 15,
        ]);

        ClubProfile::create([
            'team_id' => $team->id,
            'reputation_level' => ClubProfile::REPUTATION_ESTABLISHED,
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Test Player',
            'overall_score' => 80,
        ]);

        // An official post with replies, like a real feed.
        $post = SocialPost::create([
            'game_id' => $game->id,
            'context' => 'club_official',
            'author_name' => 'Valencia CF Femenino',
            'author_handle' => '@VCF_Femenino',
            'text' => 'Test announcement',
        ]);
        SocialPost::create([
            'game_id' => $game->id,
            'context' => 'club_official_reply',
            'parent_post_id' => $post->id,
            'author_name' => 'Fan',
            'author_handle' => '@fan123',
            'text' => 'Great signing!',
        ]);

        $response = $this->actingAs($user)->get("/game/{$game->id}/club-social");

        $response->assertStatus(200);
    }
}
