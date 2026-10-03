<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Modules\Season\Services\TournamentCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: TournamentCreationService::budgetForNation() compared the
 * team's display name against a hardcoded English list, so a localized
 * name ('España', 'États-Unis'…) silently dropped the federation budget
 * from €15M to the UEFA/rest-of-world tier. The lookup must key on the
 * stable FIFA code instead.
 */
class TournamentCreationBudgetTest extends TestCase
{
    use RefreshDatabase;

    private TournamentCreationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TournamentCreationService::class);
    }

    public function test_top_tier_resolves_by_fifa_code_despite_localized_name(): void
    {
        $spain = Team::factory()->create([
            'name' => 'España', // localized display name
            'fifa_code' => 'ESP',
            'confederation' => 'UEFA',
            'type' => 'national',
        ]);

        $this->assertSame(15_000_000, $this->service->budgetForNation($spain));
    }

    public function test_top_tier_non_uefa_by_fifa_code(): void
    {
        $usa = Team::factory()->create([
            'name' => 'Estados Unidos',
            'fifa_code' => 'USA',
            'confederation' => 'CONCACAF',
            'type' => 'national',
        ]);

        $this->assertSame(15_000_000, $this->service->budgetForNation($usa));
    }

    public function test_uefa_non_top_gets_eight_million(): void
    {
        $scotland = Team::factory()->create([
            'name' => 'Scotland',
            'fifa_code' => 'SCO',
            'confederation' => 'UEFA',
            'type' => 'national',
        ]);

        $this->assertSame(8_000_000, $this->service->budgetForNation($scotland));
    }

    public function test_rest_of_world_gets_three_million(): void
    {
        $mexico = Team::factory()->create([
            'name' => 'Mexico',
            'fifa_code' => 'MEX',
            'confederation' => 'CONCACAF',
            'type' => 'national',
        ]);

        $this->assertSame(3_000_000, $this->service->budgetForNation($mexico));
    }

    public function test_legacy_team_without_fifa_code_falls_back_to_name(): void
    {
        $spain = Team::factory()->create([
            'name' => 'Spain',
            'fifa_code' => null,
            'confederation' => 'UEFA',
            'type' => 'national',
        ]);

        $this->assertSame(15_000_000, $this->service->budgetForNation($spain));
    }
}
