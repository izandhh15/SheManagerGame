<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\ClubSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M9: idioma de los comunicados de los clubes extranjeros.
 *
 * Conflicto de especificación (qa/bugs/agent-13.md): el QA esperaba
 * inglés para los clubes extranjeros, pero el comentario de
 * config/club_languages.php —especificación vigente— dice
 * "Resto del mundo: español".
 *
 * Resolución (03-10-2026): vale el comentario del config. Los clubes no
 * mapeados publican en español (ClubSocialService::clubLang() devuelve
 * el 'default' => 'es'); el inglés de las plantillas solo aparece con
 * el locale del sitio en 'en', igual que para cualquier club español.
 * El comentario del config documenta la decisión.
 */
class M9ForeignClubLanguageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un club extranjero (Arsenal, EN) publica su comunicado en español
     * con el locale por defecto.
     */
    public function test_foreign_club_announcement_is_spanish(): void
    {
        app()->setLocale('es');

        Competition::factory()->league()->create(['id' => 'EN1', 'country' => 'EN', 'tier' => 1]);
        $user = User::factory()->create();

        $team = Team::factory()->create(['name' => 'Arsenal FC', 'country' => 'EN']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'EN1',
            'country' => 'EN',
            'season' => '2026',
            'social_hype' => 0,
        ]);
        ClubProfile::create([
            'team_id' => $team->id,
            'reputation_level' => ClubProfile::REPUTATION_ESTABLISHED,
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Estrella Fichaje',
            'overall_score' => 80,
            'contract_until' => '2029-06-30',
        ]);

        $result = app(ClubSocialService::class)->announce($game, 'signing', $player->id);
        $this->assertTrue($result['ok'], 'el anuncio debería publicarse');

        $text = SocialPost::find($result['post_id'])->text;
        $this->assertStringContainsString(
            'es nueva jugadora del',
            $text,
            'el club extranjero debe publicar en español (config: resto del mundo → español)'
        );
        $this->assertStringNotContainsString('is a new', $text, 'no debe salir en inglés');
        $this->assertStringNotContainsString('𝗢𝗙𝗙𝗜𝗖𝗜𝗔𝗟', $text, 'no debe salir en inglés');
    }
}
