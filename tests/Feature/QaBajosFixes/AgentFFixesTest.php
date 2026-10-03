<?php

namespace Tests\Feature\QaBajosFixes;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\SeasonTicketPricing;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\ClubSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 4 (bugs BAJOS) — Agente F.
 *
 * B9  Respuestas de fans en valenciano/catalán/gallego según el idioma del club
 *     (antes SIEMPRE en español).
 * B16 Sin dobles espacios en los anuncios de sede / próxima jornada en casa
 *     cuando el rival no tiene nombre.
 * B17 Parte médico con weeks=0 o negativos se sanea a un mínimo de 1 semana.
 * B18 Abonos sin datos de precios: el anuncio no muestra "Desde 0 €".
 */
class AgentFFixesTest extends TestCase
{
    use RefreshDatabase;

    /** Local pgsql test DB (see phpunit.xml); independent of TestCase's override. */
    protected $connectionsToTransact = ['pgsql'];

    private int $compSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    /**
     * @return array{0:Game, 1:Team}
     */
    private function makeGame(string $teamName, string $country = 'ES'): array
    {
        $this->compSeq++;
        $compId = $country . '_QAFF_' . $this->compSeq;

        Competition::factory()->league()->create([
            'id' => $compId,
            'country' => $country,
            'tier' => 1,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => $teamName, 'country' => $country]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $compId,
            'country' => $country,
            'season' => '2026',
            'social_hype' => 0,
        ]);

        ClubProfile::create([
            'team_id' => $team->id,
            'reputation_level' => ClubProfile::REPUTATION_MODEST,
        ]);

        for ($i = 0; $i < 5; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $team->id,
                'overall_score' => 70,
            ]);
        }

        return [$game, $team];
    }

    private function starPlayer(Game $game, Team $team, string $name): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => $name,
            'overall_score' => 88,
        ]);
    }

    /**
     * @return list<string> reply texts of every fan reply across $announces
     *   signing announcements (each with a different player to avoid the
     *   duplicate check).
     */
    private function fanReplyTexts(string $teamName, string $playerPrefix, int $announces = 3): array
    {
        $texts = [];
        for ($i = 0; $i < $announces; $i++) {
            [$game, $team] = $this->makeGame($teamName);
            $player = $this->starPlayer($game, $team, $playerPrefix . ' ' . $this->compSeq . '-' . $i);
            $result = app(ClubSocialService::class)->announce($game, 'signing', $player->id);
            $this->assertTrue($result['ok'], 'announce(signing) failed: ' . ($result['message'] ?? '?'));

            $replies = SocialPost::where('parent_post_id', $result['post_id'])
                ->where('context', 'club_official_reply')
                ->pluck('text')
                ->all();
            $this->assertGreaterThanOrEqual(4, count($replies));
            array_push($texts, ...$replies);
        }

        return $texts;
    }

    /**
     * Marcadores del antiguo comportamiento (100 % de respuestas en
     * español): no puede aparecer ninguno bajo un comunicado va/ca/gl.
     */
    private function spanishMarkerRegex(): string
    {
        return '/\b(fichajazo|fichaje|ilusiona|barbaridad|deportiva|camiseta|bienvenida|romperla|m.xima|veremos|verdad)\b/i';
    }

    private function assertFanRepliesInClubLanguage(string $teamName, array $ownMarkers, string $playerPrefix): void
    {
        $texts = $this->fanReplyTexts($teamName, $playerPrefix);
        $esRegex = $this->spanishMarkerRegex();

        foreach ($texts as $text) {
            $this->assertDoesNotMatchRegularExpression(
                $esRegex,
                $text,
                "Respuesta de fan en español bajo un comunicado de $teamName: $text"
            );
        }

        $joined = implode("\n", $texts);
        $found = false;
        foreach ($ownMarkers as $marker) {
            if (mb_stripos($joined, $marker) !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Ninguna respuesta de fan contiene marcadores de $teamName");
    }

    // ------------------------------------------------------------------
    // B9: respuestas de fans en el idioma del club
    // ------------------------------------------------------------------

    public function test_fan_replies_under_valencian_announcement_are_valencian(): void
    {
        $this->assertFanRepliesInClubLanguage(
            'Valencia CF Femenino',
            ['fitxatge', 'il·lusiona', 'samarreta', 'Benvinguda'],
            'Xiqueta QAFF'
        );
    }

    public function test_fan_replies_under_catalan_announcement_are_catalan(): void
    {
        $this->assertFanRepliesInClubLanguage(
            'FC Barcelona',
            ['fitxatge', 'il·lusiona', 'samarreta', 'Benvinguda'],
            'Jugadora QAFF'
        );
    }

    public function test_fan_replies_under_galician_announcement_are_galician(): void
    {
        $this->assertFanRepliesInClubLanguage(
            'Dépor Abanca',
            ['fichaxe', 'Benvida', 'camiño', 'Xa se verá'],
            'Rapaza QAFF'
        );
    }

    public function test_fan_replies_under_spanish_announcement_stay_spanish(): void
    {
        // Sin regresión: un club español con locale 'es' sigue recibiendo
        // respuestas en español.
        $texts = $this->fanReplyTexts('Real Madrid CF', 'Crack QAFF', 2);
        $joined = implode("\n", $texts);

        $this->assertMatchesRegularExpression(
            $this->spanishMarkerRegex(),
            $joined,
            'Las respuestas de fans de un club español deberían seguir en español'
        );
    }

    public function test_valencian_fan_replies_never_use_sota(): void
    {
        $texts = $this->fanReplyTexts('Valencia CF Femenino', 'Xiqueta VA QAFF');

        foreach ($texts as $text) {
            $this->assertDoesNotMatchRegularExpression(
                '/\bsota\b/i',
                $text,
                "Respuesta valenciana con «sota»: $text"
            );
        }
    }

    // ------------------------------------------------------------------
    // B16: sin dobles espacios con rival sin nombre
    // ------------------------------------------------------------------

    public function test_venue_confirmed_with_empty_away_name_has_no_double_spaces(): void
    {
        [$game, $team] = $this->makeGame('CD Getafe Femenino');
        $away = Team::factory()->create(['name' => '', 'country' => 'ES']);
        $match = GameMatch::factory()->forGame($game)->create([
            'home_team_id' => $team->id,
            'away_team_id' => $away->id,
            'stadium_name' => 'Camp de Mestalla',
            'scheduled_date' => now()->addDays(5),
        ]);

        $post = app(ClubSocialService::class)->announceVenueConfirmed($game->fresh(), $match);

        $this->assertNotNull($post);
        $this->assertStringNotContainsString('  ', $post->text, 'No debe haber dobles espacios por rival sin nombre');
    }

    public function test_next_home_with_empty_away_name_has_no_double_spaces(): void
    {
        [$game, $team] = $this->makeGame('CD Getafe Femenino');
        $away = Team::factory()->create(['name' => '', 'country' => 'ES']);
        GameMatch::factory()->forGame($game)->create([
            'home_team_id' => $team->id,
            'away_team_id' => $away->id,
            'played' => false,
            'scheduled_date' => now()->addDays(3),
        ]);

        $result = app(ClubSocialService::class)->announce($game->fresh(), 'next_home');

        $this->assertTrue($result['ok']);
        $post = SocialPost::find($result['post_id']);
        $this->assertStringNotContainsString('  ', $post->text, 'No debe haber dobles espacios por rival sin nombre');
    }

    // ------------------------------------------------------------------
    // B17: parte médico con 0 semanas (o negativas) -> mínimo 1
    // ------------------------------------------------------------------

    public function test_injury_with_zero_weeks_clamps_to_one(): void
    {
        [$game, $team] = $this->makeGame('CD Getafe Femenino');
        $player = $this->starPlayer($game, $team, 'Ana López QAFF');

        $result = app(ClubSocialService::class)->announce($game, 'injury', $player->id, ['weeks' => 0]);

        $this->assertTrue($result['ok']);
        $post = SocialPost::find($result['post_id']);
        $this->assertStringNotContainsString('0 semanas', $post->text, 'Texto absurdo: "unas 0 semanas de baja"');
        $this->assertStringContainsString('1 semanas', $post->text);
    }

    public function test_injury_with_negative_weeks_clamps_to_one(): void
    {
        [$game, $team] = $this->makeGame('CD Getafe Femenino');
        $player = $this->starPlayer($game, $team, 'Ana Negativa QAFF');

        $result = app(ClubSocialService::class)->announce($game, 'injury', $player->id, ['weeks' => -5]);

        $this->assertTrue($result['ok']);
        $post = SocialPost::find($result['post_id']);
        $this->assertStringNotContainsString('-5 semanas', $post->text);
        $this->assertStringContainsString('1 semanas', $post->text);
    }

    // ------------------------------------------------------------------
    // B18: abonos sin datos de precios -> sin mención de precio
    // ------------------------------------------------------------------

    public function test_season_tickets_without_pricing_data_has_no_price(): void
    {
        [$game] = $this->makeGame('CD Getafe Femenino');

        $result = app(ClubSocialService::class)->announce($game, 'season_tickets');

        $this->assertTrue($result['ok']);
        $post = SocialPost::find($result['post_id']);
        $this->assertStringNotContainsString('0 €', $post->text, 'Texto absurdo: "Desde 0 €"');
        $this->assertStringNotContainsString('Desde', $post->text);
        $this->assertStringContainsString('abonos', $post->text);
    }

    public function test_season_tickets_with_pricing_data_shows_price(): void
    {
        // Sin regresión: con datos de precios, el "Desde X €" sigue saliendo.
        [$game] = $this->makeGame('CD Getafe Femenino');

        SeasonTicketPricing::create([
            'game_id' => $game->id,
            'season' => 2026,
            'areas' => [
                ['name' => 'Tribuna', 'price_cents' => 9900],
                ['name' => 'Grada', 'price_cents' => 5900],
            ],
            'total_capacity' => 5000,
            'total_sold' => 0,
            'total_revenue' => 0,
            'pricing_preset' => 'standard',
            'is_default' => true,
        ]);

        $result = app(ClubSocialService::class)->announce($game->fresh(), 'season_tickets');

        $this->assertTrue($result['ok']);
        $post = SocialPost::find($result['post_id']);
        $this->assertStringContainsString('Desde 59 €', $post->text);
    }
}
