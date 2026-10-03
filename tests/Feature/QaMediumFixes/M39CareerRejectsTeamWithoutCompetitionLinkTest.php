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
 * Regresión M39: carrera con un equipo sin link en `competition_teams` —
 * la partida "completaba" el setup pero sin ningún partido (injugable).
 *
 * Bug original: GameCreationService aplica el fallback a la competición
 * tier-1 del país (competition_id=ESP1) pero el equipo nunca entra en
 * `competition_entries`, así que el generador de fixtures no le crea
 * ningún partido aunque `setup_completed_at` quede fijado. El usuario
 * cree que la creación funcionó pero no tiene nada que jugar.
 *
 * Fix: InitGame valida al crear la partida que el equipo tenga link en
 * `competition_teams` para la temporada vigente; si no, rechaza con
 * `messages.team_has_no_competition_link` antes de crear nada.
 */
class M39CareerRejectsTeamWithoutCompetitionLinkTest extends TestCase
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
     * Equipo con plantilla suficiente (20 templates) pero SIN fila en
     * `competition_teams`: la creación se rechaza con error en `team_id`
     * y no se crea la partida (en vez de una partida "completa" sin
     * partidos).
     */
    public function test_career_rejects_team_without_competition_link(): void
    {
        $user = $this->admin();
        $club = Team::factory()->create();
        $this->seedTemplates($club, 20, '2025');

        $response = $this->actingAs($user)->post(route('init-game'), [
            'game_mode' => Game::MODE_CAREER,
            'team_id' => $club->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['team_id']);
        $this->assertSame(0, Game::where('user_id', $user->id)->count(), 'No debe crearse ninguna partida');
    }

    /**
     * Control positivo: con link en `competition_teams` y plantilla
     * suficiente, la creación pasa la validación y la partida se crea.
     */
    public function test_career_accepts_team_with_competition_link(): void
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
        $this->seedTemplates($club, 20, $competition->season);

        $response = $this->actingAs($user)->post(route('init-game'), [
            'game_mode' => Game::MODE_CAREER,
            'team_id' => $club->id,
        ]);

        $response->assertSessionHasNoErrors();
        $game = Game::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($club->id, $game->team_id);
        $this->assertSame('ESP1', $game->competition_id);
        $response->assertRedirect(route('game.welcome', $game->id));
    }
}
