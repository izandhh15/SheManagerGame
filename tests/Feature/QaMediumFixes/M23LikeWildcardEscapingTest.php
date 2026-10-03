<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\ClubSocialService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M23: detección de duplicados con LIKE sin escapar → falsos
 * "ya anunciado" (nombre null → patrón '%%' que casa con todo; nombre "_"
 * → '%%_%%' que casa con cualquier texto). (qa/bugs/agent-18.md)
 *
 * Fix: los comodines LIKE (\, %, _) se escapan antes de buscar, y el
 * nombre null salta la comprobación (ya lo hacía alreadyAnnounced(); aquí
 * se aplica también a announceRenewal(), que además acepta nombre null en
 * sus plantillas en vez de lanzar TypeError).
 */
class M23LikeWildcardEscapingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Renovar a una jugadora sin nombre no queda bloqueada por la
     * renovación de otra jugadora (antes: LIKE '%%' → "ya anunciado").
     */
    public function test_renewal_with_null_player_name_is_not_wrongly_deduped(): void
    {
        [$game, $team] = $this->buildScenario();

        $first = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Ana Normal',
        ]);
        $this->assertTrue(
            app(ClubSocialService::class)->announce($game, 'renewal', $first->id)['ok'],
            'la primera renovación debería publicarse'
        );

        $nameless = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => null,
        ]);
        $result = app(ClubSocialService::class)->announce($game, 'renewal', $nameless->id);

        $this->assertTrue($result['ok'], 'la renovación de una jugadora sin nombre no es un duplicado');
        $this->assertSame(
            2,
            SocialPost::where('game_id', $game->id)
                ->where('post_kind', 'renewal')
                ->where('context', 'club_official')
                ->count(),
            'deberían existir los dos comunicados de renovación'
        );
    }

    /**
     * Fichar a una jugadora llamada "_" no queda bloqueado por el fichaje
     * de otra jugadora (antes: LIKE '%%_%%' casaba con cualquier texto).
     */
    public function test_signing_with_underscore_name_is_not_wrongly_deduped(): void
    {
        [$game, $team] = $this->buildScenario();

        $first = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Ana López',
        ]);
        $this->assertTrue(
            app(ClubSocialService::class)->announce($game, 'signing', $first->id)['ok'],
            'el primer fichaje debería publicarse'
        );

        $underscore = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => '_',
        ]);
        $result = app(ClubSocialService::class)->announce($game, 'signing', $underscore->id);

        $this->assertTrue($result['ok'], 'el fichaje de "_" no es un duplicado del de "Ana López"');
        $this->assertNotSame(
            'Ya hay un comunicado sobre esto.',
            $result['message'],
            'no debe responder con el mensaje de duplicado'
        );
    }

    /**
     * El '%' del nombre tampoco actúa como comodín: "100% Crack" no
     * duplica el anuncio de otra jugadora.
     */
    public function test_signing_with_percent_in_name_is_not_wrongly_deduped(): void
    {
        [$game, $team] = $this->buildScenario();

        $first = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'María Pérez',
        ]);
        $this->assertTrue(
            app(ClubSocialService::class)->announce($game, 'signing', $first->id)['ok']
        );

        $percent = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => '100% Crack',
        ]);
        $result = app(ClubSocialService::class)->announce($game, 'signing', $percent->id);

        $this->assertTrue($result['ok'], 'el fichaje de "100% Crack" no es un duplicado');
    }

    /**
     * Guarda: el duplicado real sigue detectándose (el escape no rompe la
     * detección legítima).
     */
    public function test_real_duplicate_renewal_is_still_blocked(): void
    {
        [$game, $team] = $this->buildScenario();

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Ana Normal',
        ]);

        $service = app(ClubSocialService::class);
        $this->assertTrue($service->announce($game, 'renewal', $player->id)['ok']);

        $second = $service->announce($game, 'renewal', $player->id);
        $this->assertFalse($second['ok'], 'la segunda renovación sí es un duplicado');
        $this->assertSame('Ya hay un comunicado sobre esto.', $second['message']);
    }

    /**
     * @return array{0:Game, 1:Team}
     */
    private function buildScenario(): array
    {
        app()->setLocale('es');

        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $team = Team::factory()->create(['name' => 'Test WFC', 'country' => 'ES']);
        $user = User::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        return [$game, $team];
    }
}
