<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\Team;
use App\Models\User;
use App\Modules\Transfer\Listeners\RollAIContractRenewals;
use App\Modules\Transfer\Services\DispositionService;
use App\Modules\Transfer\Services\ScoutingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 10 de la revisión de medios (misma causa raíz, 3 sitios):
 * RollAIContractRenewals, DispositionService (drip + roll de infelicidad
 * salarial) y ScoutingService (radar) hardcodeaban $game->team_id, ignorando
 * el filial (reserve_team_id) del usuario.
 *
 * - La IA no debe renovar jugadoras del filial sin consentimiento (ni gastar
 *   el presupuesto del usuario): el scan de renovaciones excluye TODOS los
 *   equipos del usuario (userTeamIds()).
 * - El drip y el roll de infelicidad salarial deben CUBRIR al filial.
 * - El radar de contratos debe IGNORAR al filial (no son objetivos poachables).
 */
class FilialScopeFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_renewals_ignore_the_filial_but_still_run_for_ai_clubs(): void
    {
        srand(20261004); // el roll de renovación es probabilístico (35‰): fijar semilla para determinismo
        [$game, $parent, $filial] = $this->filialGame();
        $aiClub = Team::factory()->create(['name' => 'AI Club WFC', 'country' => 'ES']);

        // Jugadora del filial con contrato a punto de expirar: la IA no debe tocarla.
        GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $filial->id,
            'name' => 'Filial Star',
            'overall_score' => 85,
            'date_of_birth' => '2000-06-01',
            'contract_until' => '2026-01-15',
            'pending_annual_wage' => null,
        ]);

        // Jugadora de un club IA en la misma situación: candidata normal.
        $aiPlayer = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $aiClub->id,
            'name' => 'AI Star',
            'overall_score' => 85,
            'date_of_birth' => '2000-06-01',
            'contract_until' => '2026-01-15',
            'pending_annual_wage' => null,
        ]);

        $result = app(RollAIContractRenewals::class)->roll($game->refresh());

        $this->assertSame(
            1,
            $result['considered'],
            'Solo la jugadora del club IA debe entrar en el scan; la del filial, no.'
        );
        $this->assertSame('2026-01-15', $aiPlayer->refresh()->contract_until->toDateString());
    }

    public function test_wage_gap_morale_drip_covers_the_filial(): void
    {
        [$game, $parent, $filial] = $this->filialGame();

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $filial->id,
            'name' => 'Filial Unhappy',
            'salary_unhappy_since' => '2026-09-01',
        ]);
        // La factory ya crea la fila satélite: solo ajustamos la moral.
        $player->matchState()->update(['morale' => 80]);

        $affected = app(DispositionService::class)->applyWageGapMoraleDrip($game->refresh());

        $this->assertSame(1, $affected, 'El drip debe alcanzar a las jugadoras del filial.');
        $this->assertSame(
            75,
            (int) $player->matchState->refresh()->morale,
            'La moral debe bajar el drip completo (80 - 5).'
        );
    }

    public function test_salary_unhappiness_roll_clears_stale_flags_on_the_filial(): void
    {
        [$game, $parent, $filial] = $this->filialGame();

        // Sin brecha salarial (sin compañeras en la banda de habilidad, la
        // mediana es 0 → no elegible) pero con el flag levantado: el roll
        // debe limpiarlo también en el filial.
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $filial->id,
            'name' => 'Filial Flagged',
            'overall_score' => 70,
            'annual_wage' => 1_000_000_00,
            'salary_unhappy_since' => '2026-09-01',
        ]);

        $result = app(DispositionService::class)->rollSalaryUnhappiness($game->refresh());

        $this->assertSame(1, $result['cleared'], 'El roll debe limpiar flags rancios del filial.');
        $this->assertNull($player->refresh()->salary_unhappy_since);
    }

    public function test_contract_radar_excludes_the_filial(): void
    {
        [$game, $parent, $filial] = $this->filialGame();
        $aiClub = Team::factory()->create(['name' => 'AI Club WFC', 'country' => 'ES']);

        $filialTarget = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $filial->id,
            'name' => 'Filial Expiring',
            'overall_score' => 82,
            'contract_until' => '2027-06-30',
            'pending_annual_wage' => null,
        ]);
        $aiTarget = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $aiClub->id,
            'name' => 'AI Expiring',
            'overall_score' => 80,
            'contract_until' => '2027-06-30',
            'pending_annual_wage' => null,
        ]);

        $targets = app(ScoutingService::class)->getExpiringContractTargets($game->refresh());
        $names = collect($targets)->map(fn (array $t) => $t['player']->name)->all();

        $this->assertContains('AI Expiring', $names, 'El radar debe seguir mostrando objetivos de clubes IA.');
        $this->assertNotContains(
            'Filial Expiring',
            $names,
            'El radar no debe ofrecer como poachables a las propias jugadoras del filial.'
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return array{Game, Team, Team} */
    private function filialGame(): array
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $parent = Team::factory()->create(['name' => 'Parent WFC', 'country' => 'ES']);
        $filial = Team::factory()->create([
            'name' => 'Parent WFC B',
            'country' => 'ES',
            'parent_team_id' => $parent->id,
        ]);

        $game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => $parent->id,
            'reserve_team_id' => $filial->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        return [$game, $parent, $filial];
    }
}
