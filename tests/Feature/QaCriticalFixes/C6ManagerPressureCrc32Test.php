<?php

namespace Tests\Feature\QaCriticalFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\DTOs\MatchNarrative;
use App\Modules\Media\Services\ManagerPressureService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug crítico C6 — segunda parte (fix en el commit 8583c98).
 *
 * El mismo patrón `crc32(...) % count` sin sanear existía en
 * ManagerPressureService:85 (`candidateTeamIds`, elección de rivales) y
 * :123 (`buildArticle`, elección del medio). En PHP 32-bit (Wasmer Edge)
 * crc32() puede devolver negativo → índice -1 → "Undefined array key" →
 * 500. El fix sanea con `& 0x7FFFFFFF` en ambos puntos.
 *
 * Complementa a C6Crc32IndexTest (que cubre PressNewsService). Los métodos
 * son privados, así que se invocan por reflexión; la emulación de 32-bit
 * (signed32/findNegativeCrcRound) es la misma técnica del QA de agent-16.
 * Se usan 3 rivales porque con count=2 el bit 31 no afecta a `% 2` y el
 * test no distinguiría fix de no-fix.
 */
class C6ManagerPressureCrc32Test extends TestCase
{
    use RefreshDatabase;

    public function test_rivals_pick_index_is_safe_on_32bit_php(): void
    {
        [$game, $team, $rivals] = $this->buildScenario();

        // Ronda cuyo crc32 tendría el bit alto activo en PHP 32-bit.
        $round = $this->findNegativeCrcRound($game->id . 'rivals%s');
        $this->assertNotNull($round, 'Debería existir una ronda con crc32 negativo en 5000 intentos.');

        $signed = $this->signed32(crc32($game->id . 'rivals' . $round));
        $this->assertLessThan(
            0, $signed,
            'La ronda elegida debe emular el escenario del bug (crc32 negativo en 32-bit).'
        );

        // Sin el fix, $rivals[$start] con $start negativo lanza
        // ErrorException ("Undefined array key -1").
        $ids = $this->invokePrivate('candidateTeamIds', [$game, null, $round]);

        $this->assertContains($team->id, $ids, 'El propio equipo siempre es candidato.');
        foreach ($rivals as $rival) {
            $this->assertContains(
                $rival->id, $ids,
                'Los 3 rivales deben salir elegidos sin excepción de índice.'
            );
        }

        // Determinista entre llamadas (el feed no debe parpadear).
        $again = $this->invokePrivate('candidateTeamIds', [$game, null, $round]);
        $this->assertSame($ids, $again);
    }

    public function test_outlet_pick_index_is_safe_on_32bit_php(): void
    {
        [$game, $team] = $this->buildScenario();

        // Ronda cuyo crc32 tendría el bit alto activo en PHP 32-bit.
        $round = $this->findNegativeCrcRound($game->id . 'outlet%s' . $team->id);
        $this->assertNotNull($round, 'Debería existir una ronda con crc32 negativo en 5000 intentos.');

        $signed = $this->signed32(crc32($game->id . 'outlet' . $round . $team->id));
        $this->assertLessThan(
            0, $signed,
            'La ronda elegida debe emular el escenario del bug (crc32 negativo en 32-bit).'
        );

        // Sin el fix, $outlets[índice negativo] lanza ErrorException.
        // manager_name queda null a propósito: nunca se inventa un nombre.
        $article = $this->invokePrivate(
            'buildArticle',
            [$game->refresh(), $team, ['L', 'L', 'L', 'L', 'L'], $round]
        );

        $this->assertInstanceOf(MatchNarrative::class, $article);
        $this->assertNotEmpty($article->headline);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * @return array{Game, Team, array<Team>}
     */
    private function buildScenario(): array
    {
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

        $rivals = [];
        foreach (['Rival Alpha WFC', 'Rival Beta WFC', 'Rival Gamma WFC'] as $name) {
            $rival = Team::factory()->create(['name' => $name, 'country' => 'ES']);
            GameStanding::create([
                'game_id' => $game->id,
                'competition_id' => 'ESP1',
                'team_id' => $rival->id,
            ]);
            $rivals[] = $rival;
        }

        return [$game, $team, $rivals];
    }

    private function invokePrivate(string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod(ManagerPressureService::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke(app(ManagerPressureService::class), ...$args);
    }

    /** Emula la interpretación con signo de crc32() en PHP de 32 bits. */
    private function signed32(int $unsigned): int
    {
        return $unsigned >= 2 ** 31 ? $unsigned - 2 ** 32 : $unsigned;
    }

    /** Busca una ronda cuyo crc32(semilla) sería negativo en PHP 32-bit. */
    private function findNegativeCrcRound(string $seedPattern): ?int
    {
        for ($r = 0; $r < 5000; $r++) {
            if (crc32(sprintf($seedPattern, $r)) >= 2 ** 31) {
                return $r;
            }
        }

        return null;
    }
}
