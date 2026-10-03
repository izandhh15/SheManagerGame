<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\Game;
use App\Models\Team;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Season\Processors\PlayerRetirementProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R7 regression: ContractExpirationProcessor and PlayerRetirementProcessor
 * must early-return when $game->current_date is null instead of throwing a
 * TypeError (which 500s the season-transition step on every retry, leaving
 * the transition stuck).
 */
class R7NullCurrentDateTest extends TestCase
{
    use RefreshDatabase;

    private function makeGameWithNullDate(): Game
    {
        $team = Team::factory()->create();

        return Game::factory()->forTeam($team)->create([
            'season' => '2025',
            'current_date' => null,
        ]);
    }

    private function transitionData(Game $game): SeasonTransitionData
    {
        return new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $game->competition_id,
        );
    }

    public function test_contract_expiration_processor_early_returns_on_null_current_date(): void
    {
        $game = $this->makeGameWithNullDate();

        $result = app(ContractExpirationProcessor::class)->process($game, $this->transitionData($game));

        // Pass-through: nothing ran, no metadata touched, no exception.
        $this->assertInstanceOf(SeasonTransitionData::class, $result);
        $this->assertSame('2025', $result->oldSeason);
        $this->assertSame('2026', $result->newSeason);
    }

    public function test_player_retirement_processor_early_returns_on_null_current_date(): void
    {
        $game = $this->makeGameWithNullDate();

        $result = app(PlayerRetirementProcessor::class)->process($game, $this->transitionData($game));

        // Pass-through: retirement metadata was never populated.
        $this->assertInstanceOf(SeasonTransitionData::class, $result);
        $this->assertNull($result->getMetadata('retiredPlayers'));
        $this->assertNull($result->getMetadata('retirementAnnouncements'));
    }
}
