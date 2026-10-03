<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameJournalist;
use App\Models\GameMatch;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\JournalistService;
use App\Modules\Media\Services\NationalSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * National press: journalists post match previews for national-team saves,
 * and previews + reports appear in the "Redes de la selección" feed.
 */
class NationalPressPreviewTest extends TestCase
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
        ]);

        GameJournalist::create([
            'game_id' => $game->id,
            'name' => 'Marcos Téllez',
            'handle' => '@marco_tellez',
            'specialty' => 'cronicas',
            'followers' => 31000,
            'active' => true,
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
            'scheduled_date' => '2026-11-21',
            'played' => false,
            'stadium_name' => 'La Cartuja',
        ]);

        return [$game, $match];
    }

    public function test_preview_posted_for_upcoming_national_match(): void
    {
        [$game] = $this->nationalGame();

        $post = app(JournalistService::class)->maybePostNationalPreview($game);

        $this->assertNotNull($post);
        $this->assertEquals('journalist_preview', $post->context);
        $this->assertStringContainsString('PREVIA', $post->text);
        $this->assertStringContainsString('La Cartuja', $post->text);
    }

    public function test_preview_not_posted_twice(): void
    {
        [$game] = $this->nationalGame();
        $svc = app(JournalistService::class);

        $first = $svc->maybePostNationalPreview($game);
        $second = $svc->maybePostNationalPreview($game);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertEquals(1, SocialPost::where('game_id', $game->id)
            ->where('context', 'journalist_preview')->count());
    }

    public function test_preview_ignored_for_club_saves(): void
    {
        [$game] = $this->nationalGame();
        $club = Team::factory()->create(['name' => 'Valencia CF', 'type' => 'club']);
        $game->team_id = $club->id;
        $game->save();

        $post = app(JournalistService::class)->maybePostNationalPreview($game->fresh());

        $this->assertNull($post);
    }

    public function test_journalist_posts_appear_in_national_feed(): void
    {
        [$game, $match] = $this->nationalGame();

        $preview = app(JournalistService::class)->maybePostNationalPreview($game);
        $this->assertNotNull($preview);

        $feed = app(NationalSocialService::class)->feed($game->fresh());

        $this->assertTrue($feed->contains(fn ($p) => $p->id === $preview->id));
    }
}
