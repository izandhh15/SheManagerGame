<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug medio M34 (QA agent-29).
 *
 * La página de resultados (`GET /game/{gameId}/results/{competition}/{matchday}`)
 * daba 500 (`Attempt to read property "round_name" on null`) cuando la
 * jornada no tenía partidos para la partida: `resources/views/results.blade.php:10`
 * hacía `$matches->first()->round_name` sin null-safe. Ahora usa `?->` y la
 * página muestra el estado vacío ("Jornada N") en vez de reventar.
 */
class M34ResultsEmptyRoundTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $userTeam;
    protected Competition $competition;
    protected Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'M34 Valencia']);
        $this->competition = Competition::factory()->league()->create(['id' => 'M34L']);

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'user_id' => $this->user->id,
            'competition_id' => $this->competition->id,
        ]);
    }

    public function test_results_page_with_empty_round_returns_200(): void
    {
        // Jornada inexistente / sin partidos: antes 500, ahora 200 con el
        // estado vacío.
        $response = $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/results/NOPE/99");

        $response->assertOk();
        $response->assertSee(__('game.matchday_n', ['number' => 99]));
    }

    public function test_results_page_with_empty_round_of_existing_competition(): void
    {
        // Competición real pero jornada sin partidos registrados.
        $response = $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/results/M34L/99");

        $response->assertOk();
    }
}
