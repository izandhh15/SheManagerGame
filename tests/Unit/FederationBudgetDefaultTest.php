<?php

namespace Tests\Unit;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BAJA review: ShowScheduleFriendly and ShowNationalVenues passed
 * federationBudget to their views with inconsistent fallbacks (?? 2000000
 * vs ?? 0). Both now use Game::DEFAULT_FEDERATION_BUDGET, which must stay
 * in sync with the games.federation_budget column default.
 */
class FederationBudgetDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_federation_budget_constant_matches_db_column_default(): void
    {
        $row = DB::selectOne(
            "SELECT column_default FROM information_schema.columns
             WHERE table_name = 'games' AND column_name = 'federation_budget'"
        );

        $this->assertNotNull($row->column_default);
        $dbDefault = (int) filter_var($row->column_default, FILTER_SANITIZE_NUMBER_INT);

        $this->assertSame($dbDefault, Game::DEFAULT_FEDERATION_BUDGET);
    }

    public function test_default_federation_budget_is_two_million(): void
    {
        $this->assertSame(2000000, Game::DEFAULT_FEDERATION_BUDGET);
    }
}
