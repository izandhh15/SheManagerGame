<?php

namespace Tests\Feature\QaBajosFixes;

use App\Console\Commands\StressTest;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Report\Services\SeasonSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * QA bajos, agente J: bugs B27, B28, B29 (fin de temporada, stress-test, login).
 */
class AgentJFixesTest extends TestCase
{
    use RefreshDatabase;

    // ── B27: la pantalla de fin de temporada muestra ascendidos/descendidos
    // en ligas españolas aunque los tiers profundos (ESP3A/B/C) no tengan
    // standings en una partida de club.

    public function test_season_summary_includes_promotion_data_for_esp1(): void
    {
        $user = User::factory()->create();

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'Liga F']);
        Competition::factory()->league()->create(['id' => 'ESP2', 'name' => 'Primera Federación']);
        foreach (['ESP3A', 'ESP3B', 'ESP3C'] as $id) {
            Competition::factory()->league()->create(['id' => $id, 'name' => $id]);
        }

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'competition_id' => 'ESP1',
            'season' => '2026',
        ]);

        $esp1Teams = $this->seedTier($game, 'ESP1', 16, withStandings: true);
        $game->team_id = $esp1Teams[0]->id;
        $game->save();
        $this->seedTier($game, 'ESP2', 14, withStandings: true);
        // Escenario de partida de club: ESP3A/B/C tienen entries pero nunca
        // se simulan, así que no tienen standings.
        $this->seedTier($game, 'ESP3A', 14, withStandings: false);
        $this->seedTier($game, 'ESP3B', 14, withStandings: false);
        $this->seedTier($game, 'ESP3C', 14, withStandings: false);

        $summary = app(SeasonSummaryService::class)->buildSeasonSummary($game->refresh());

        $this->assertNotNull(
            $summary['promotionData'],
            'promotionData debe estar presente aunque ESP3A/B/C no tengan standings'
        );
        $this->assertNotEmpty($summary['promotionData']['promoted']);
        $this->assertNotEmpty($summary['promotionData']['relegated']);

        // Los descendidos son los dos últimos de ESP1 (posiciones 15 y 16).
        $relegatedPositions = array_column($summary['promotionData']['relegated'], 'position');
        sort($relegatedPositions);
        $this->assertSame([15, 16], $relegatedPositions);
    }

    /**
     * @return list<Team>
     */
    private function seedTier(Game $game, string $competitionId, int $teamCount, bool $withStandings): array
    {
        $teams = [];
        for ($i = 0; $i < $teamCount; $i++) {
            $team = Team::factory()->create(['country' => 'ES']);
            $teams[] = $team;
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
            ]);
            if ($withStandings) {
                GameStanding::create([
                    'game_id' => $game->id,
                    'competition_id' => $competitionId,
                    'team_id' => $team->id,
                    'position' => $i + 1,
                    'played' => 30,
                    'won' => 10,
                    'drawn' => 5,
                    'lost' => 15,
                    'goals_for' => 30,
                    'goals_against' => 40,
                    'points' => 35 - $i,
                ]);
            }
        }
        return $teams;
    }

    // ── B28: app:stress-test drena la cola `setup` (SetupNewGame declara
    // onQueue('setup')) además de `default` en processQueuedJobs().

    public function test_stress_test_process_queued_jobs_drains_setup_queue(): void
    {
        config(['queue.default' => 'database']);
        StressTestSetupMarkerJob::$ran = false;

        StressTestSetupMarkerJob::dispatch()->onQueue('setup');
        $this->assertSame(1, DB::table('jobs')->where('queue', 'setup')->count());

        $kernel = $this->app[\Illuminate\Contracts\Console\Kernel::class];
        $getArtisan = new \ReflectionMethod($kernel, 'getArtisan');
        $getArtisan->setAccessible(true);

        $command = new StressTest();
        $command->setLaravel($this->app);
        $command->setApplication($getArtisan->invoke($kernel));
        $command->setInput(new \Symfony\Component\Console\Input\ArrayInput([]));
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput()
        ));

        $method = new \ReflectionMethod(StressTest::class, 'processQueuedJobs');
        $method->setAccessible(true);
        $method->invoke($command);

        $this->assertSame(
            0,
            DB::table('jobs')->where('queue', 'setup')->count(),
            'processQueuedJobs() debe drenar la cola setup'
        );
        $this->assertTrue(StressTestSetupMarkerJob::$ran, 'el job de la cola setup debe haberse ejecutado');
    }

    // ── B29: el login acepta el email en mayúsculas (el registro lo guarda
    // en minúsculas; Postgres compara con `=` case-sensitive).

    public function test_login_with_uppercase_email_succeeds(): void
    {
        $email = 'minusculas.qa@example.com';

        $this->post('/register/career', [
            'name' => 'QA Bajos',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', ['email' => $email]);

        $this->post('/logout');
        $this->assertGuest();

        $response = $this->post('/login', [
            'email' => mb_strtoupper($email),
            'password' => 'password123',
        ]);

        // El login con el email en mayúsculas debe autenticar.
        $this->assertAuthenticated();
    }
}

/**
 * Job marcador para el test B28: se despacha a la cola `setup` y marca
 * un flag estático al ejecutarse (el worker corre en el mismo proceso).
 */
class StressTestSetupMarkerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public static bool $ran = false;

    public function handle(): void
    {
        self::$ran = true;
    }
}
