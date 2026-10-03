<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\ManagerStats;
use App\Models\ManagerTrophy;
use App\Models\Team;
use App\Models\User;
use App\Modules\Manager\Services\CareerSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 9 de la revisión de medios: los trofeos (y las stats) se filtraban por
 * el team_id actual, así que al cambiar de equipo en modo pro-manager la
 * tira "carrera" y la vitrina perdían todo el historial. El palmarés sigue
 * al mánager (user_id + game_id).
 */
class CareerSummaryFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_trophy_cabinet_survives_a_team_switch(): void
    {
        [$game, $user, $clubA, $clubB] = $this->scenario();

        $cabinetBefore = app(CareerSummaryService::class)->buildTrophyCabinet($game, $user->id);
        $this->assertCount(1, $cabinetBefore, 'Los 2 trofeos de la misma competición se agrupan en 1 entrada.');
        $this->assertSame(2, $cabinetBefore[0]['count']);

        // El mánager cambia de club a mitad de carrera.
        $game->update(['team_id' => $clubB->id]);

        $cabinetAfter = app(CareerSummaryService::class)->buildTrophyCabinet($game->refresh(), $user->id);

        $this->assertCount(
            1,
            $cabinetAfter,
            'La vitrina debe conservar los trofeos ganados con el club anterior.'
        );
        $this->assertSame(2, $cabinetAfter[0]['count']);
        $this->assertSame('Liga F (test)', $cabinetAfter[0]['competition_name']);
    }

    public function test_build_counts_trophies_across_teams(): void
    {
        [$game, $user, $clubA, $clubB] = $this->scenario();

        $game->update(['team_id' => $clubB->id]);

        $summary = app(CareerSummaryService::class)->build($game->refresh(), $user->id);

        $this->assertSame(2, $summary['trophies'], 'El contador debe seguir al mánager, no al equipo.');
        $this->assertSame(12, $summary['matches_played'], 'Las stats son por partida (una fila por game).');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return array{Game, User, Team, Team} */
    private function scenario(): array
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $user = User::factory()->create();
        $clubA = Team::factory()->create(['name' => 'Club A WFC', 'country' => 'ES']);
        $clubB = Team::factory()->create(['name' => 'Club B WFC', 'country' => 'ES']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $clubA->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        ManagerStats::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'team_id' => $clubA->id,
            'matches_played' => 12,
            'seasons_completed' => 1,
        ]);

        foreach (['2024', '2025'] as $season) {
            ManagerTrophy::create([
                'user_id' => $user->id,
                'game_id' => $game->id,
                'team_id' => $clubA->id,
                'competition_id' => 'ESP1',
                'season' => $season,
                'trophy_type' => 'league',
            ]);
        }

        return [$game, $user, $clubA, $clubB];
    }
}
