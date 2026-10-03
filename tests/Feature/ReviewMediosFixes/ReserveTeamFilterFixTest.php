<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\ReserveTeamFilter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 4 de la revisión de medios (grupo 05): findTopDivision() ignoraba
 * playoff_source_divisions, así que las llamadas con ESP3B/ESP3C devolvían
 * colección vacía y el filtro de filiales quedaba desactivado en los grupos
 * B y C (un filial bloqueado podía entrar y ganar el ESP3PO).
 */
class ReserveTeamFilterFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_top_division_resolves_playoff_source_divisions(): void
    {
        $filter = app(ReserveTeamFilter::class);
        $ref = new \ReflectionMethod(ReserveTeamFilter::class, 'findTopDivision');
        $ref->setAccessible(true);
        $countryConfig = app(CountryConfig::class);

        $this->assertSame('ESP2', $ref->invoke($filter, $countryConfig, 'ESP3A'));
        $this->assertSame('ESP2', $ref->invoke($filter, $countryConfig, 'ESP3B'));
        $this->assertSame('ESP2', $ref->invoke($filter, $countryConfig, 'ESP3C'));
    }

    public function test_top_division_team_ids_work_for_group_b_and_c(): void
    {
        Competition::factory()->league()->create(['id' => 'ESP2', 'country' => 'ES', 'tier' => 2]);
        $game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'competition_id' => 'ESP2',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        $esp2Teams = Team::factory()->count(3)->create(['country' => 'ES']);
        foreach ($esp2Teams as $team) {
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => 'ESP2',
                'team_id' => $team->id,
            ]);
        }

        $filter = app(ReserveTeamFilter::class);

        // Antes del fix, ESP3B/ESP3C devolvían colección vacía.
        foreach (['ESP3A', 'ESP3B', 'ESP3C'] as $group) {
            $ids = $filter->getTopDivisionTeamIds($game, $group);
            $this->assertCount(3, $ids, "grupo {$group} no resuelve los equipos de ESP2");
            $this->assertEqualsCanonicalizing(
                $esp2Teams->pluck('id')->all(),
                $ids->all(),
                "grupo {$group}"
            );
        }
    }
}
