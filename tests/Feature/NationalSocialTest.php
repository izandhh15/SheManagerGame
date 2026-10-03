<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\NationalSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for "Redes de la selección" (national team social).
 */
class NationalSocialTest extends TestCase
{
    use RefreshDatabase;

    private function nationalGame(): Game
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'name' => 'Spain',
            'type' => 'national',
        ]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'national_squad_window' => '2026-10-05',
            'national_squad_player_ids' => [],
        ]);

        // Real template rows so the star names resolve.
        $ids = [];
        foreach (['Aitana Bonmatí', 'Laia Aleixandri', 'Ona Batlle'] as $i => $name) {
            $id = \Illuminate\Support\Str::uuid()->toString();
            $ids[] = $id;
            \Illuminate\Support\Facades\DB::table('game_player_templates')->insert([
                'season' => '2026',
                'team_id' => $team->id,
                'player_id' => $id,
                'name' => $name,
                'position' => 'CM',
                'overall_score' => 90 - $i,
            ]);
        }
        $game->national_squad_player_ids = $ids;
        $game->save();

        return $game;
    }

    public function test_page_loads_for_national_team(): void
    {
        $game = $this->nationalGame();
        $user = $game->user;

        $response = $this->actingAs($user)->get("/game/{$game->id}/national-social");
        $response->assertStatus(200);
        $response->assertSee('@SEFutbolFem', false);
    }

    public function test_page_404_for_club_game(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club']);
        $game = Game::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

        $response = $this->actingAs($user)->get("/game/{$game->id}/national-social");
        $response->assertStatus(404);
    }

    public function test_handles_use_real_accounts(): void
    {
        $game = $this->nationalGame();
        $service = app(NationalSocialService::class);

        $this->assertSame('@SEFutbolFem', $service->nationalHandle($game));
        $this->assertSame('@sefutbolfem', $service->instagramHandle($game));
        $this->assertFalse($service->isFederationAccount($game));
    }

    public function test_federation_account_flagged(): void
    {
        // Portugal sigue siendo cuenta de federación (sin cuenta femenina
        // propia). Francia ya no: desde M13 usa @equipedefrancef.
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Portugal', 'type' => 'national']);
        $game = Game::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

        $service = app(NationalSocialService::class);
        $this->assertSame('@selecaoportugal', $service->nationalHandle($game));
        $this->assertTrue($service->isFederationAccount($game));
    }

    public function test_announce_convocatoria(): void
    {
        $game = $this->nationalGame();
        $user = $game->user;
        $service = app(NationalSocialService::class);

        $result = $service->announce($game, 'convocatoria');
        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['post_id']);

        $feed = $service->feed($game);
        $this->assertCount(1, $feed);
        $this->assertGreaterThanOrEqual(4, $feed->first()->replies->count());
    }

    public function test_announce_sede(): void
    {
        $game = $this->nationalGame();
        $user = $game->user;
        $service = app(NationalSocialService::class);

        GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $game->team_id,
            'played' => false,
            'scheduled_date' => '2026-10-10',
            'stadium_name' => 'Estadio Nuevo Mirador',
            'neutral_venue_capacity' => 45000,
        ]);

        $result = $service->announce($game, 'sede');
        $this->assertTrue($result['ok']);

        $feed = $service->feed($game);
        $this->assertStringContainsString('Estadio Nuevo Mirador', $feed->first()->text);
    }

    public function test_announce_entradas_sets_ticket_price(): void
    {
        $game = $this->nationalGame();
        $service = app(NationalSocialService::class);

        $result = $service->announce($game, 'entradas', ['tier' => 'popular']);
        $this->assertTrue($result['ok']);
        $this->assertSame(15, $game->fresh()->ticket_price);
    }

    public function test_announce_rejected_for_club_game(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club']);
        $game = Game::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

        $service = app(NationalSocialService::class);
        $result = $service->announce($game, 'convocatoria');
        $this->assertFalse($result['ok']);
    }
}
