<?php

namespace Tests\Feature\QaCriticalFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Services\FastModeService;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión C1: el avance de jornada ya no revienta por
 * PlayerSocialService::bestPlayer().
 *
 * Bug original: bestPlayer() consultaba la tabla inexistente
 * `match_player_ratings` DENTRO de la transacción de avance. PostgreSQL
 * aborta la transacción entera al primer fallo (42P01): el catch interno lo
 * tragaba, pero la sentencia siguiente moría con 25P02 y el rollback era
 * TOTAL. El modo rápido y el safety net de partido abandonado quedaban
 * inutilizables.
 *
 * Fix (commit 8583c98): bestPlayer() consulta la tabla REAL
 * `game_player_match_ratings` (game_match_id/game_player_id) y comprueba
 * su existencia con Schema::hasTable() ANTES de la query, para no envenenar
 * la transacción.
 *
 * Estos dos tests son el port (invertido) de los repros del QA
 * (MatchdaySimulationInvariantsTest::test_repro_fast_mode_advance_aborts_on_missing_ratings_table
 * y ::test_repro_abandoned_live_match_safety_net_also_aborts): con el fix,
 * el avance completa sin excepción y los partidos quedan jugados.
 */
class C1FastModeAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_COUNT = 8;

    private User $user;

    /** @var Team[] */
    private array $teams = [];

    private Competition $league;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        for ($i = 0; $i < self::TEAM_COUNT; $i++) {
            $this->teams[] = Team::factory()->create(['name' => "C1 Team {$i}"]);
        }

        $this->league = Competition::factory()->league()->create([
            'id' => 'C1L',
            'name' => 'C1 Liga',
            'season' => '2024',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->teams[0]->id,
            'competition_id' => $this->league->id,
            'season' => '2024',
            'base_season' => '2024',
            'current_date' => '2024-08-15',
        ]);

        foreach ($this->teams as $team) {
            $this->createSquad($team);
        }

        $this->generateDoubleRoundRobin();
        $this->initializeStandings();
    }

    /**
     * Fast mode: el avance completa la jornada del usuario sin 25P02.
     *
     * Bug original: lanzaba 25P02 y el rollback dejaba 0 partidos jugados.
     */
    public function test_fast_mode_advance_completes_without_aborting_transaction(): void
    {
        app(FastModeService::class)->enter($this->game);

        $result = app(MatchdayAdvanceCoordinator::class)
            ->runSync($this->game->id, fastForward: true);

        $this->assertNotNull($result, 'no se pudo reclamar el flag');
        $this->assertSame('done', $result->type, "tipo inesperado: {$result->type}");

        // El partido del usuario (viernes, jornada 1) queda jugado y con
        // clasificación aplicada (finalize in-place). Con el bug, el rollback
        // total dejaba 0 partidos jugados.
        $userMatch = GameMatch::where('game_id', $this->game->id)
            ->where('round_number', 1)
            ->where(fn ($q) => $q->where('home_team_id', $this->teams[0]->id)
                ->orWhere('away_team_id', $this->teams[0]->id))
            ->first();
        $this->assertNotNull($userMatch);
        $this->assertTrue((bool) $userMatch->played, 'el partido del usuario debería estar jugado');
        $this->assertTrue(
            (bool) $userMatch->standings_applied,
            'el partido del usuario debería tener clasificación aplicada'
        );

        // La clasificación del equipo del usuario refleja el partido.
        $standing = GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', $this->league->id)
            ->where('team_id', $this->teams[0]->id)
            ->first();
        $this->assertSame(1, $standing->played, 'PJ del equipo del usuario');

        // El flag de avance se limpió.
        $this->assertNull(
            Game::find($this->game->id)->matchday_advancing_at,
            'el flag matchday_advancing_at debería quedar limpio'
        );
    }

    /**
     * Safety net: si el usuario abandona el live match sin pulsar
     * "Continuar", el siguiente avance finaliza el partido pendiente DENTRO
     * de la transacción y juega la jornada nueva.
     *
     * Bug original: el finalize del pendiente también reventaba con 25P02 y
     * el rollback total dejaba la jornada 2 sin jugar Y el partido pendiente
     * sin finalizar.
     */
    public function test_abandoned_live_match_safety_net_finalizes_and_advances(): void
    {
        $coordinator = app(MatchdayAdvanceCoordinator::class);

        // 1. Avance normal: el partido del usuario queda pendiente de finalizar.
        $first = $coordinator->runSync($this->game->id);
        $this->assertNotNull($first, 'no se pudo reclamar el flag');
        $this->assertSame('live_match', $first->type, "tipo inesperado: {$first->type}");
        $firstPendingId = Game::find($this->game->id)->pending_finalization_match_id;
        $this->assertNotNull($firstPendingId, 'el partido del usuario debería quedar pendiente');

        // 2. El usuario "cierra el navegador" sin Continuar y avanza de nuevo.
        $second = $coordinator->runSync($this->game->id);
        $this->assertNotNull($second, 'no se pudo reclamar el flag en el segundo avance');
        $this->assertSame('live_match', $second->type, "tipo inesperado en el segundo avance: {$second->type}");

        // La jornada 1 quedó jugada entera con clasificación aplicada...
        $roundOne = GameMatch::where('game_id', $this->game->id)
            ->where('round_number', 1)
            ->get();
        $this->assertCount(4, $roundOne);
        foreach ($roundOne as $m) {
            $this->assertTrue((bool) $m->played, "partido {$m->id} de la jornada 1 sin jugar");
            $this->assertTrue(
                (bool) $m->standings_applied,
                "partido {$m->id} de la jornada 1 sin clasificación aplicada"
            );
        }

        // ...incluido el partido que estaba pendiente (su finalize hizo commit).
        $firstMatch = GameMatch::find($firstPendingId);
        $this->assertTrue((bool) $firstMatch->standings_applied, 'el partido pendiente debería haberse finalizado');

        // ...y el nuevo pendiente es el partido del usuario de la jornada 2
        // (sin clasificación aplicada aún: eso llega al pulsar "Continuar").
        $secondPendingId = Game::find($this->game->id)->pending_finalization_match_id;
        $this->assertNotNull($secondPendingId, 'debería haber un nuevo partido pendiente de la jornada 2');
        $secondMatch = GameMatch::find($secondPendingId);
        $this->assertSame(2, $secondMatch->round_number, 'el nuevo pendiente debería ser de la jornada 2');
        $this->assertTrue((bool) $secondMatch->played);
        $this->assertFalse(
            (bool) $secondMatch->standings_applied,
            'el partido de la jornada 2 aún no debería tener clasificación aplicada'
        );

        // El flag de avance se limpió.
        $this->assertNull(
            Game::find($this->game->id)->matchday_advancing_at,
            'el flag matchday_advancing_at debería quedar limpio'
        );
    }

    // ------------------------------------------------------------------
    // Fixtures (port de MatchdaySimulationInvariantsTest del QA, agente 3)
    // ------------------------------------------------------------------

    private function createSquad(Team $team): void
    {
        $mk = fn (string $position) => GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->create(['position' => $position]);

        $mk('Goalkeeper');
        $mk('Goalkeeper');
        foreach (['Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back', 'Centre-Back'] as $p) {
            $mk($p);
        }
        foreach (['Central Midfield', 'Central Midfield', 'Central Midfield', 'Left Midfield', 'Right Midfield'] as $p) {
            $mk($p);
        }
        foreach (['Centre-Forward', 'Centre-Forward', 'Centre-Forward'] as $p) {
            $mk($p);
        }
    }

    /**
     * Doble round-robin con método del círculo. La ronda r (1-based) reparte
     * sus 4 partidos entre viernes/sábado/domingo para ejercitar la expansión
     * de jornada multi-día de MatchdayService::getNextMatchBatch().
     */
    private function generateDoubleRoundRobin(): void
    {
        $n = self::TEAM_COUNT;
        $ids = array_map(fn ($t) => $t->id, $this->teams);
        $dayOffsets = [0, 1, 1, 2]; // vie, sáb, sáb, dom

        $rounds = [];
        $rot = $ids;
        for ($r = 0; $r < $n - 1; $r++) {
            $pairings = [];
            for ($i = 0; $i < $n / 2; $i++) {
                $home = $rot[$i];
                $away = $rot[$n - 1 - $i];
                // Alternar local/visitante para equilibrar
                $pairings[] = $r % 2 === 0 ? [$home, $away] : [$away, $home];
            }
            $rounds[] = $pairings;
            // Rotar manteniendo fijo el primero
            $rot = array_merge([$rot[0]], [$rot[$n - 1]], array_slice($rot, 1, $n - 2));
        }
        // Segunda vuelta con campos invertidos
        foreach ($rounds as $pairings) {
            $rounds[] = array_map(fn ($p) => [$p[1], $p[0]], $pairings);
        }

        $base = Carbon::parse('2024-08-16'); // viernes
        foreach ($rounds as $r => $pairings) {
            foreach ($pairings as $m => [$homeId, $awayId]) {
                GameMatch::factory()->create([
                    'game_id' => $this->game->id,
                    'competition_id' => $this->league->id,
                    'round_number' => $r + 1,
                    'home_team_id' => $homeId,
                    'away_team_id' => $awayId,
                    'scheduled_date' => $base->copy()->addWeeks($r)->addDays($dayOffsets[$m]),
                ]);
            }
        }
    }

    private function initializeStandings(): void
    {
        $position = 1;
        foreach ($this->teams as $team) {
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->league->id,
                'team_id' => $team->id,
                'position' => $position,
                'prev_position' => null,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
                'form' => null,
            ]);
            $position++;
        }
    }
}
