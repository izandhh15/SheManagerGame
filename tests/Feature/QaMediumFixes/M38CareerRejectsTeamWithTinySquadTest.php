<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresión M38: carrera con un club de plantilla < 17 jugadoras — el
 * setup petaba con `copy() on null` (500) y dejaba la partida bricked
 * (pantalla de "preparando temporada" eterna).
 *
 * Bug original: YouthAcademyPromotionProcessor (2º del pipeline de setup)
 * corre antes de que ningún procesador persista `current_date`; si la
 * plantilla de templates queda por debajo del mínimo (17), su fase 3
 * genera jugadoras sintéticas con $game->current_date->copy() → null →
 * 500. Solo no petaba porque los clubes normales (≥17) nunca entran en
 * la fase 3.
 *
 * Fix: InitGame valida al crear la partida que el equipo tenga al menos
 * 17 templates de jugadora para la temporada; si no, rechaza con
 * `messages.team_squad_too_small` antes de crear nada.
 */
class M38CareerRejectsTeamWithTinySquadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function seedTemplates(Team $team, int $count, string $season): void
    {
        // player_id is a soft identity UUID (the players table was dropped
        // in 2026_05_04_000004): no master row needed.
        $templates = [];
        for ($i = 1; $i <= $count; $i++) {
            $templates[] = [
                'season' => $season,
                'player_id' => (string) Str::uuid(),
                'team_id' => $team->id,
                'number' => $i,
                'position' => 'Central Midfield',
            ];
        }
        DB::table('game_player_templates')->insert($templates);
    }

    /**
     * Club con link a competición pero solo 8 templates (< 17): la
     * creación se rechaza con error en `team_id` y no se crea la partida
     * (en vez del 500 con la partida bricked).
     */
    public function test_career_rejects_team_with_squad_below_minimum(): void
    {
        $user = $this->admin();
        $club = Team::factory()->create();
        $competition = Competition::factory()->league()->create(['id' => 'ESP1', 'tier' => 1]);
        DB::table('competition_teams')->insert([
            'competition_id' => $competition->id,
            'team_id' => $club->id,
            'season' => $competition->season,
        ]);
        $this->seedTemplates($club, 8, $competition->season);

        $response = $this->actingAs($user)->post(route('init-game'), [
            'game_mode' => Game::MODE_CAREER,
            'team_id' => $club->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['team_id']);
        $this->assertSame(0, Game::where('user_id', $user->id)->count(), 'No debe crearse ninguna partida');
    }

    /**
     * Control positivo: con 17 templates (el mínimo) la creación pasa la
     * validación y la partida se crea.
     */
    public function test_career_accepts_team_with_squad_at_minimum(): void
    {
        Bus::fake();
        $user = $this->admin();
        $club = Team::factory()->create();
        $competition = Competition::factory()->league()->create(['id' => 'ESP1', 'tier' => 1]);
        DB::table('competition_teams')->insert([
            'competition_id' => $competition->id,
            'team_id' => $club->id,
            'season' => $competition->season,
        ]);
        $this->seedTemplates($club, 17, $competition->season);

        $response = $this->actingAs($user)->post(route('init-game'), [
            'game_mode' => Game::MODE_CAREER,
            'team_id' => $club->id,
        ]);

        $response->assertSessionHasNoErrors();
        $game = Game::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($club->id, $game->team_id);
        $response->assertRedirect(route('game.welcome', $game->id));
    }
}
