<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Manager\Services\AcademyCareerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresión M37: la carrera academia en un club sin filial no rechazaba —
 * empezaba en el PRIMER EQUIPO.
 *
 * Bug original: AcademyCareerService::findLowestFilial() hacía
 * getFilialChain($club)->last() y getFilialChain() siempre contiene al
 * menos al primer equipo, así que NUNCA devolvía null. La rama
 * `if (!$lowestFilial) return back()->withErrors(['academy_club_id' => ...])`
 * de InitGame era código muerto y la partida de "academia" dirigía el
 * primer equipo, rompiendo la premisa del modo.
 *
 * Fix: findLowestFilial() devuelve null cuando la cadena solo contiene al
 * primer equipo (sin filial por debajo); InitGame rechaza entonces con
 * `messages.club_has_no_filial` antes de crear nada.
 */
class M37AcademyCareerRejectsClubWithoutFilialTest extends TestCase
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

    private function linkToCompetition(Team $team, Competition $competition): void
    {
        DB::table('competition_teams')->insert([
            'competition_id' => $competition->id,
            'team_id' => $team->id,
            'season' => $competition->season,
        ]);
    }

    /**
     * Nivel servicio: un club sin hijos no tiene filial → null.
     */
    public function test_find_lowest_filial_returns_null_for_club_without_filial(): void
    {
        $club = Team::factory()->create(['parent_team_id' => null]);

        $this->assertNull(app(AcademyCareerService::class)->findLowestFilial($club));
    }

    /**
     * Nivel servicio (control): con filial, devuelve la más baja de la
     * cadena, no el primer equipo.
     */
    public function test_find_lowest_filial_returns_deepest_child(): void
    {
        $club = Team::factory()->create(['parent_team_id' => null]);
        $b = Team::factory()->create(['parent_team_id' => $club->id]);
        $c = Team::factory()->create(['parent_team_id' => $b->id]);

        $lowest = app(AcademyCareerService::class)->findLowestFilial($club);

        $this->assertNotNull($lowest);
        $this->assertSame($c->id, $lowest->id);
    }

    /**
     * POST /new-game (career_pro + academy_club_id) con un club sin filial:
     * 302 con error en `academy_club_id` y NINGUNA partida creada.
     */
    public function test_academy_career_rejects_club_without_filial(): void
    {
        $user = $this->admin();
        $club = Team::factory()->create(['parent_team_id' => null]);

        $response = $this->actingAs($user)->post(route('init-game'), [
            'game_mode' => Game::MODE_CAREER_PRO,
            'academy_club_id' => $club->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['academy_club_id']);
        $this->assertSame(0, Game::where('user_id', $user->id)->count(), 'No debe crearse ninguna partida');
    }

    /**
     * Control positivo: club CON filial, filial con link a competición y
     * plantilla suficiente → la partida se crea empezando en la filial más
     * baja (no en el primer equipo).
     */
    public function test_academy_career_with_filial_starts_at_lowest_filial(): void
    {
        Bus::fake();
        $user = $this->admin();
        $club = Team::factory()->create(['parent_team_id' => null]);
        $filial = Team::factory()->create(['parent_team_id' => $club->id]);

        $competition = Competition::factory()->league()->create(['id' => 'ESP1', 'tier' => 1]);
        $this->linkToCompetition($filial, $competition);
        $this->seedTemplates($filial, 20, $competition->season);

        $response = $this->actingAs($user)->post(route('init-game'), [
            'game_mode' => Game::MODE_CAREER_PRO,
            'academy_club_id' => $club->id,
        ]);

        $response->assertSessionHasNoErrors();
        $game = Game::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($filial->id, $game->team_id, 'La carrera academia debe empezar en la filial');
        $this->assertSame($club->id, $game->academy_career_club_id);
        $response->assertRedirect(route('game.welcome', $game->id));
    }
}
