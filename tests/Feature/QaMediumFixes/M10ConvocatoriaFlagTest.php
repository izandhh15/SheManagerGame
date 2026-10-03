<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Game;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\NationalSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresión M10: la convocatoria mostraba la bandera 🇪🇸 para TODAS las
 * selecciones (en español).
 *
 * Bug original: lang/es/game.php tenía el emoji 🇪🇸 hardcodeado en
 * national_social_convocatoria_text(_plain) y national_social_entradas_text,
 * y las plantillas de respuestas de fans también. (qa/bugs/agent-14.md)
 *
 * Fix: las cadenas usan el placeholder :flag y NationalSocialService lo
 * rellena con nationFlag($game), derivado del código ISO del país del
 * equipo (indicadores regionales; EN → 🇬🇧).
 */
class M10ConvocatoriaFlagTest extends TestCase
{
    use RefreshDatabase;

    private function nationalGame(string $teamName, string $country): Game
    {
        app()->setLocale('es');

        $user = User::factory()->create();
        $team = Team::factory()->create([
            'name' => $teamName,
            'type' => 'national',
            'country' => $country,
        ]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'country' => $country,
            'national_squad_window' => '2026-10-05',
            'national_squad_player_ids' => collect(range(1, 23))
                ->map(fn () => Str::uuid()->toString())
                ->all(),
        ]);

        return $game->fresh('team');
    }

    /**
     * La convocatoria de Alemania lleva 🇩🇪, nunca 🇪🇸.
     *
     * Bug original: el texto terminaba en "¡A por todas! 🇪🇸 #Convocatoria"
     * jugando con Alemania.
     */
    public function test_convocatoria_uses_nation_flag_not_spain(): void
    {
        $game = $this->nationalGame('Germany', 'DE');

        $result = app(NationalSocialService::class)->announce($game, 'convocatoria');
        $this->assertTrue($result['ok'], 'el anuncio debería publicarse');

        $text = SocialPost::find($result['post_id'])->text;
        $this->assertStringContainsString('🇩🇪', $text, 'debería llevar la bandera de Alemania');
        $this->assertStringNotContainsString('🇪🇸', $text, 'no debe llevar la bandera de España');
    }

    /**
     * El anuncio de entradas también usa la bandera de la nación.
     */
    public function test_entradas_uses_nation_flag_not_spain(): void
    {
        $game = $this->nationalGame('France', 'FR');

        $result = app(NationalSocialService::class)->announce($game, 'entradas', ['tier' => 'normal']);
        $this->assertTrue($result['ok'], 'el anuncio debería publicarse');

        $text = SocialPost::find($result['post_id'])->text;
        $this->assertStringContainsString('🇫🇷', $text, 'debería llevar la bandera de Francia');
        $this->assertStringNotContainsString('🇪🇸', $text, 'no debe llevar la bandera de España');
    }
}
