<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Competition\Services\CupEntryRoundService;
use App\Modules\Competition\Services\NeutralVenueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Fix 5 de la revisión de medios (grupo 05): conductDraw() y
 * assignEntryRounds() sin transacción dejaban sorteos parciales (ties
 * huérfanos) y entry_round inconsistentes ante un fallo a mitad.
 */
class CupDrawTransactionFixTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP', 'country' => 'ES', 'season' => '2025']);

        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2025',
            'base_season' => '2025',
        ]);
    }

    public function test_conduct_draw_rolls_back_when_neutral_venue_assignment_fails(): void
    {
        $teams = Team::factory()->count(4)->create(['country' => 'ES']);
        foreach ($teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        // El resolver de sedes revienta DESPUÉS de los bulk inserts: sin
        // transacción quedarían ties y partidos huérfanos.
        $explodingResolver = new class extends NeutralVenueResolver {
            public function resolve(string $competitionId, string $roundName, string $homeTeamId, string $awayTeamId): ?array
            {
                throw new \RuntimeException('venue boom');
            }
        };

        $service = new CupDrawService(app(CountryConfig::class), $explodingResolver);

        try {
            $service->conductDraw($this->game->id, 'ESPCUP', 1);
            $this->fail('conductDraw debería haber lanzado la excepción del resolver');
        } catch (\RuntimeException $e) {
            $this->assertSame('venue boom', $e->getMessage());
        }

        $this->assertSame(0, CupTie::where('game_id', $this->game->id)->count(), 'quedaron ties huérfanos');
        $this->assertSame(
            0,
            GameMatch::where('game_id', $this->game->id)->where('competition_id', 'ESPCUP')->count(),
            'quedaron partidos huérfanos'
        );
    }

    public function test_conduct_draw_still_creates_ties_and_matches(): void
    {
        $teams = Team::factory()->count(4)->create(['country' => 'ES']);
        foreach ($teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        $ties = app(CupDrawService::class)->conductDraw($this->game->id, 'ESPCUP', 1);

        $this->assertCount(2, $ties);
        $this->assertSame(2, GameMatch::where('game_id', $this->game->id)->where('competition_id', 'ESPCUP')->count());
    }

    public function test_assign_entry_rounds_still_assigns_rounds(): void
    {
        // Guarda de regresión: el envoltorio transaccional no cambia el
        // comportamiento normal del servicio.
        $teams = Team::factory()->count(8)->create(['country' => 'ES']);
        foreach ($teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        app(CupEntryRoundService::class)->assignEntryRounds($this->game->id, 'ES');

        $rounds = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'ESPCUP')
            ->pluck('entry_round')
            ->all();

        $this->assertCount(8, $rounds);
        foreach ($rounds as $round) {
            $this->assertGreaterThanOrEqual(1, (int) $round);
        }
    }
}
