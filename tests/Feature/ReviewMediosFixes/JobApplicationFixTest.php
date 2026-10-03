<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\ManagerJobOffer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Manager\Services\JobApplicationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fixes 6 y 7 de la revisión de medios (JobApplicationService).
 *
 * 6. reputationToInt() mapeaba tiers inexistentes (world_class/national/
 *    regional); los reales son local/modest/established/continental/elite
 *    (ClubProfile) y caían todos en `default => 1`.
 * 7. apply() aceptaba cualquier team_id (selecciones, filiales,
 *    placeholders): un pending_team_switch a una selección rompe la carrera.
 *    Ahora filtra como getAvailableJobs() y deduplica por temporada.
 */
class JobApplicationFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_reputation_to_int_uses_real_tiers(): void
    {
        $map = [
            'local' => 1,
            'modest' => 2,
            'established' => 3,
            'continental' => 4,
            'elite' => 5,
        ];

        foreach ($map as $tier => $expected) {
            $this->assertSame(
                $expected,
                $this->reputationToInt($tier),
                "El tier real '{$tier}' debe mapear a {$expected}."
            );
        }
    }

    public function test_reputation_to_int_differentiates_local_from_elite(): void
    {
        $this->assertGreaterThan(
            $this->reputationToInt('local'),
            $this->reputationToInt('elite'),
            'Un mánager elite debe puntuar por encima de uno local.'
        );
    }

    public function test_apply_rejects_national_teams(): void
    {
        [$game] = $this->basicGame();
        $national = Team::factory()->create(['type' => 'national', 'name' => 'España WNT']);

        $this->expectException(\InvalidArgumentException::class);
        app(JobApplicationService::class)->apply($game, $national);
    }

    public function test_apply_rejects_reserve_teams(): void
    {
        [$game, $parent] = $this->basicGame();
        $filial = Team::factory()->create([
            'parent_team_id' => $parent->id,
            'name' => 'Test WFC B',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(JobApplicationService::class)->apply($game, $filial);
    }

    public function test_apply_rejects_placeholders(): void
    {
        [$game] = $this->basicGame();
        $placeholder = Team::factory()->create(['is_placeholder' => true]);

        $this->expectException(\InvalidArgumentException::class);
        app(JobApplicationService::class)->apply($game, $placeholder);
    }

    public function test_apply_deduplicates_within_a_season(): void
    {
        [$game] = $this->basicGame();
        $target = Team::factory()->create(['name' => 'Destino WFC']);
        $target->refresh(); // El default 'club' de la BD solo existe tras leer la fila.
        $service = app(JobApplicationService::class);

        $service->apply($game->refresh(), $target);

        $this->assertSame(
            1,
            ManagerJobOffer::where('game_id', $game->id)
                ->where('offer_type', ManagerJobOffer::TYPE_JOB_APPLICATION)
                ->count()
        );

        try {
            $service->apply($game->refresh(), $target);
            $this->fail('La segunda solicitud al mismo club en la temporada debe lanzar excepción.');
        } catch (\InvalidArgumentException) {
            // Esperado: deduplicación.
        }

        $this->assertSame(
            1,
            ManagerJobOffer::where('game_id', $game->id)
                ->where('offer_type', ManagerJobOffer::TYPE_JOB_APPLICATION)
                ->count(),
            'No debe crearse una segunda solicitud duplicada.'
        );
    }

    public function test_apply_to_valid_club_creates_offer(): void
    {
        [$game] = $this->basicGame();
        $target = Team::factory()->create(['name' => 'Destino WFC']);
        $target->refresh(); // El default 'club' de la BD solo existe tras leer la fila.

        $result = app(JobApplicationService::class)->apply($game->refresh(), $target);

        $this->assertInstanceOf(ManagerJobOffer::class, $result['offer']);
        $this->assertSame($target->id, $result['offer']->team_id);
        $this->assertArrayHasKey('accepted', $result);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return array{Game, Team} */
    private function basicGame(): array
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $team = Team::factory()->create(['name' => 'Test WFC', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
            'manager_reputation_points' => 0,
        ]);

        return [$game, $team];
    }

    private function reputationToInt(string $level): int
    {
        $ref = new \ReflectionMethod(JobApplicationService::class, 'reputationToInt');
        $ref->setAccessible(true);

        return $ref->invoke(app(JobApplicationService::class), $level);
    }
}
