<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\NationalSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Venue calendar + automatic social announcement when a national-team
 * match venue is confirmed.
 */
class NationalVenueCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function nationalGame(): array
    {
        $user = User::factory()->create();
        $spain = Team::factory()->create(['name' => 'Spain', 'type' => 'national']);
        $france = Team::factory()->create(['name' => 'France', 'type' => 'national']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $spain->id,
            'current_date' => '2026-11-20',
            'federation_budget' => 15000000,
        ]);

        $competition = Competition::factory()->create([
            'name' => 'Nations League',
            'handler_type' => 'group_stage_cup',
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => $competition->id,
            'home_team_id' => $spain->id,
            'away_team_id' => $france->id,
            'scheduled_date' => '2026-11-24',
            'played' => false,
            'venue_status' => 'confirmed',
            'neutral_venue_name' => 'Mestalla',
            'neutral_venue_capacity' => 49430,
        ]);

        return [$game, $match];
    }

    public function test_venue_confirmed_announcement_posts_to_feed(): void
    {
        [$game, $match] = $this->nationalGame();

        $post = app(NationalSocialService::class)->announceVenueConfirmed($game, $match);

        $this->assertNotNull($post);
        $this->assertEquals('national_official', $post->context);
        $this->assertStringContainsString('Mestalla', $post->text);
        $this->assertStringContainsString('OFICIAL', $post->text);

        $feed = app(NationalSocialService::class)->feed($game->fresh());
        $this->assertTrue($feed->contains(fn ($p) => $p->id === $post->id));
    }

    public function test_venue_announcement_is_idempotent(): void
    {
        [$game, $match] = $this->nationalGame();
        $svc = app(NationalSocialService::class);

        $first = $svc->announceVenueConfirmed($game, $match);
        $second = $svc->announceVenueConfirmed($game, $match);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertEquals(1, SocialPost::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->where('context', 'national_official')->count());
    }

    public function test_venue_announcement_skipped_without_venue(): void
    {
        [$game, $match] = $this->nationalGame();
        $match->neutral_venue_name = null;
        $match->stadium_name = null;
        $match->save();

        $post = app(NationalSocialService::class)->announceVenueConfirmed($game, $match->fresh());

        $this->assertNull($post);
    }
}
