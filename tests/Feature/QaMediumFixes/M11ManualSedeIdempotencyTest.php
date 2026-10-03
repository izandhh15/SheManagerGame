<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\NationalSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M11: anuncio de sede duplicado en redes de la selección —
 * el manual no respetaba el automático (ni viceversa).
 *
 * Bug original: announceVenueConfirmed() (automático) era idempotente
 * por match_id, pero announce('sede') (manual) creaba el post SIN
 * match_id: ni detectaba el automático previo ni el automático
 * detectaba el manual. Además el composer mostraba el botón de sede
 * habilitado aunque ya se hubiera anunciado. (qa/bugs/agent-14.md)
 *
 * Fix: el manual comprueba venueAnnounced() antes de publicar y fija
 * match_id en su post; el composer deshabilita el botón cuando
 * venueAnnounced() es true para el próximo partido en casa.
 */
class M11ManualSedeIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Game $game;

    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');

        // Locale explícito: actingAs usa el objeto en memoria y el
        // middleware SetLocale caería al inglés sin él.
        $this->user = User::factory()->create(['locale' => 'es']);
        $team = Team::factory()->create(['name' => 'Spain', 'type' => 'national', 'country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'country' => 'ES',
        ])->fresh('team');

        $rival = Team::factory()->create(['name' => 'Rival M11', 'type' => 'national', 'country' => 'DE']);
        $this->match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'home_team_id' => $this->game->team_id,
            'away_team_id' => $rival->id,
            'played' => false,
            'scheduled_date' => '2026-10-10',
            'stadium_name' => 'Nuevo Mirador',
        ]);
    }

    private function officialVenuePosts(): int
    {
        return SocialPost::where('game_id', $this->game->id)
            ->where('context', 'national_official')
            ->count();
    }

    /**
     * Automático y después manual: un solo comunicado, el manual se
     * rechaza con "ya anunciado".
     *
     * Bug original: acababa con 2 posts casi idénticos.
     */
    public function test_manual_sede_after_auto_announcement_does_not_duplicate(): void
    {
        $service = app(NationalSocialService::class);

        $auto = $service->announceVenueConfirmed($this->game, $this->match);
        $this->assertNotNull($auto, 'el anuncio automático debería publicarse');

        $manual = $service->announce($this->game, 'sede');
        $this->assertFalse($manual['ok']);
        $this->assertSame('La sede de este partido ya se anunció.', $manual['message']);
        $this->assertSame(1, $this->officialVenuePosts(), 'debe haber un único comunicado de sede');
    }

    /**
     * Manual y después automático: el automático no duplica.
     *
     * Bug original: el automático no veía el post manual (sin match_id)
     * y publicaba un segundo comunicado.
     */
    public function test_auto_announcement_after_manual_sede_does_not_duplicate(): void
    {
        $service = app(NationalSocialService::class);

        $manual = $service->announce($this->game, 'sede');
        $this->assertTrue($manual['ok'], 'el anuncio manual debería publicarse');

        $auto = $service->announceVenueConfirmed($this->game, $this->match);
        $this->assertNull($auto, 'el automático no debe duplicar el manual');
        $this->assertSame(1, $this->officialVenuePosts(), 'debe haber un único comunicado de sede');
    }

    /**
     * El composer deshabilita el botón de sede cuando ya se anunció.
     */
    public function test_composer_disables_sede_button_when_already_announced(): void
    {
        app(NationalSocialService::class)->announceVenueConfirmed($this->game, $this->match);

        $response = $this->actingAs($this->user)
            ->get(route('game.national-social', $this->game->id));

        $response->assertOk();
        $response->assertSee('La sede de este partido ya se anunció.', false);
    }
}
