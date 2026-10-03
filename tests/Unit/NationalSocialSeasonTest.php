<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayerTemplate;
use App\Models\Team;
use App\Modules\Media\Services\NationalSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * BAJA review: NationalSocialService::announceConvocatoria() looked up the
 * call-up star names with a hardcoded where('season', '2026') on
 * game_player_templates. It now uses the game's current season.
 */
class NationalSocialSeasonTest extends TestCase
{
    use RefreshDatabase;

    public function test_convocatoria_star_names_come_from_the_game_season(): void
    {
        $spain = Team::factory()->create([
            'name' => 'Spain',
            'type' => 'national',
            'country' => 'ES',
            'fifa_code' => 'ESP',
        ]);

        $playerIds = [Str::uuid()->toString(), Str::uuid()->toString(), Str::uuid()->toString()];

        $competition = Competition::factory()->league()->create(['id' => 'NATIONS']);

        $game = Game::factory()->create([
            'team_id' => $spain->id,
            'competition_id' => $competition->id,
            'season' => '2025',
            'current_date' => '2025-09-01',
            'national_squad_player_ids' => $playerIds,
            'national_squad_window' => '2025-09-10',
        ]);

        // Templates for the GAME's season…
        $stars = ['Star Alpha' => 90, 'Star Beta' => 89, 'Star Gamma' => 88];
        $i = 0;
        foreach ($stars as $name => $overall) {
            GamePlayerTemplate::create([
                'season' => '2025',
                'player_id' => $playerIds[$i++],
                'team_id' => $spain->id,
                'name' => $name,
                'position' => 'Central Midfield',
                'overall_score' => $overall,
            ]);
        }

        // …and decoys for the old hardcoded season with HIGHER overalls,
        // so the old code would have picked them.
        $i = 0;
        foreach (['Decoy One', 'Decoy Two', 'Decoy Three'] as $name) {
            GamePlayerTemplate::create([
                'season' => '2026',
                'player_id' => $playerIds[$i++],
                'team_id' => $spain->id,
                'name' => $name,
                'position' => 'Central Midfield',
                'overall_score' => 99,
            ]);
        }

        $result = app(NationalSocialService::class)->announce($game, NationalSocialService::TYPE_CONVOCATORIA);

        $this->assertTrue($result['ok'] ?? false);
        $post = \App\Models\SocialPost::find($result['post_id']);
        $this->assertNotNull($post);
        $this->assertStringContainsString('Star Alpha', $post->text);
        $this->assertStringNotContainsString('Decoy', $post->text);
    }
}
