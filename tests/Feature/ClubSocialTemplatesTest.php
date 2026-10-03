<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\ClubSocialService;
use App\Modules\Media\Services\NationalSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pre-written social templates, per-club posters and per-club
 * announcement languages ("Redes del club" / "Redes de la selección").
 */
class ClubSocialTemplatesTest extends TestCase
{
    use RefreshDatabase;

    /** Local pgsql test DB (see phpunit.xml); independent of TestCase's override. */
    protected $connectionsToTransact = ['pgsql'];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);

        $this->user = User::factory()->create();
    }

    private function makeGame(string $teamName, string $country = 'ES'): Game
    {
        $team = Team::factory()->create(['name' => $teamName, 'country' => $country]);
        $game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'country' => $country,
            'season' => '2026',
            'social_hype' => 0,
        ]);
        ClubProfile::create([
            'team_id' => $team->id,
            'reputation_level' => ClubProfile::REPUTATION_ESTABLISHED,
        ]);

        return $game->fresh('team');
    }

    public function test_club_lang_resolution(): void
    {
        $service = app(ClubSocialService::class);

        $this->assertSame('va', $service->clubLang($this->makeGame('Valencia CF Femenino')));
        $this->assertSame('ca', $service->clubLang($this->makeGame('FC Barcelona')));
        $this->assertSame('ca', $service->clubLang($this->makeGame('RCD Espanyol')));
        $this->assertSame('ca', $service->clubLang($this->makeGame('FC Badalona Women')));
        $this->assertSame('gl', $service->clubLang($this->makeGame('Dépor Abanca')));
        $this->assertSame('es', $service->clubLang($this->makeGame('Real Madrid CF')));
        $this->assertSame('es', $service->clubLang($this->makeGame('Athletic Club')));
        $this->assertSame('es', $service->clubLang($this->makeGame('Sevilla FC')));
    }

    public function test_club_poster_resolution(): void
    {
        $service = app(ClubSocialService::class);

        $this->assertStringContainsString(
            'valenciacf.com',
            (string) $service->clubPoster($this->makeGame('Valencia CF Femenino'))
        );
        $this->assertStringContainsString(
            'fcbarcelona.com',
            (string) $service->clubPoster($this->makeGame('FC Barcelona'))
        );
        $this->assertStringContainsString(
            'realmadrid.com',
            (string) $service->clubPoster($this->makeGame('Real Madrid CF'))
        );
        $this->assertStringContainsString(
            'atleticodemadrid.com',
            (string) $service->clubPoster($this->makeGame('Atlético de Madrid'))
        );
        $this->assertNull($service->clubPoster($this->makeGame('Sevilla FC')));
    }

    public function test_federation_poster_spain(): void
    {
        $service = app(NationalSocialService::class);
        $team = Team::factory()->create(['name' => 'Spain', 'country' => 'ES', 'type' => 'national']);
        $game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2026',
        ]);
        $game = $game->fresh('team');

        $this->assertStringContainsString('rfef.es', (string) $service->federationPoster($game));
    }

    public function test_announce_next_home_creates_valencian_post_with_poster(): void
    {
        $game = $this->makeGame('Valencia CF Femenino');
        $rival = Team::factory()->create(['name' => 'Rival FC', 'country' => 'ES']);
        GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $game->team_id,
            'away_team_id' => $rival->id,
            'competition_id' => 'ESP1',
            'played' => false,
            'scheduled_date' => '2026-10-10',
            'stadium_name' => 'Mestalla',
        ]);

        $result = app(ClubSocialService::class)->announce($game, ClubSocialService::TYPE_NEXT_HOME);

        $this->assertTrue($result['ok']);
        $post = SocialPost::find($result['post_id']);
        $this->assertSame('next_home', $post->post_kind);
        $this->assertStringContainsString('valenciacf.com', (string) $post->image_url);
        // Valencian, not Spanish.
        $this->assertStringContainsString('PRÒXIMA JORNADA A CASA', $post->text);
        $this->assertStringNotContainsString('PRÓXIMA JORNADA EN CASA', $post->text);

        // Idempotent: second call for the same match is rejected.
        $again = app(ClubSocialService::class)->announce($game, ClubSocialService::TYPE_NEXT_HOME);
        $this->assertFalse($again['ok']);
    }

    public function test_announce_renewal_posts_in_galician_without_touching_contract(): void
    {
        $game = $this->makeGame('Dépor Abanca');
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'name' => 'Test Galician Player',
            'overall_score' => 75,
            'contract_until' => '2027-06-30',
        ]);

        // R5: el anuncio es puro — la extensión la aplica el flujo legítimo
        // (aquí simulada) tras aceptar la negociación de renovación.
        \App\Models\RenewalNegotiation::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'status' => \App\Models\RenewalNegotiation::STATUS_ACCEPTED,
        ]);
        $player->update(['contract_until' => '2029-06-30']);

        $result = app(ClubSocialService::class)->announce(
            $game,
            ClubSocialService::TYPE_RENEWAL,
            $player->id
        );

        $this->assertTrue($result['ok']);
        // El anuncio no toca el contrato: sigue como lo dejó la negociación.
        $this->assertSame('2029-06-30', $player->fresh()->contract_until->format('Y-m-d'));

        $post = SocialPost::find($result['post_id']);
        $this->assertSame('renewal', $post->post_kind);
        $this->assertStringContainsString('RENOVADA', $post->text);
        $this->assertStringContainsString('2029', $post->text);
        // Galician, not Spanish.
        $this->assertStringContainsString('ata 2029', $post->text);
    }

    public function test_announce_ticket_discount_and_sales(): void
    {
        $game = $this->makeGame('Real Madrid CF');
        $rival = Team::factory()->create(['name' => 'Rival FC', 'country' => 'ES']);
        GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $game->team_id,
            'away_team_id' => $rival->id,
            'competition_id' => 'ESP1',
            'played' => false,
            'scheduled_date' => '2026-10-12',
        ]);

        $service = app(ClubSocialService::class);

        $discount = $service->announce($game, ClubSocialService::TYPE_TICKET_DISCOUNT);
        $this->assertTrue($discount['ok']);
        $this->assertStringContainsString(
            'DESCUENTO',
            (string) SocialPost::find($discount['post_id'])?->text
        );

        $sales = $service->announce($game, ClubSocialService::TYPE_TICKET_SALES);
        $this->assertTrue($sales['ok']);
        $this->assertStringContainsString(
            'YA A LA VENTA',
            (string) SocialPost::find($sales['post_id'])?->text
        );
    }

    public function test_announce_friendly(): void
    {
        $game = $this->makeGame('FC Barcelona');
        // The FRIENDLY pseudo-competition must exist (FK on game_matches).
        \App\Models\Competition::factory()->create(['id' => 'FRIENDLY', 'country' => 'ES']);
        $rival = Team::factory()->create(['name' => 'Rival FC', 'country' => 'ES']);
        GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $game->team_id,
            'away_team_id' => $rival->id,
            'competition_id' => 'FRIENDLY',
            'played' => false,
            'scheduled_date' => '2026-07-20',
        ]);

        $result = app(ClubSocialService::class)->announce($game, ClubSocialService::TYPE_FRIENDLY);

        $this->assertTrue($result['ok']);
        $post = SocialPost::find($result['post_id']);
        $this->assertSame('friendly', $post->post_kind);
        // Catalan, not Spanish.
        $this->assertStringContainsString('AMISTÓS DE PRETEMPORADA', $post->text);
    }
}
