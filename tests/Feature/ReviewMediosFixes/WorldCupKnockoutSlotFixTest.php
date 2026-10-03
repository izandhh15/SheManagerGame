<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Exceptions\UnresolvableBracketSlotException;
use App\Modules\Competition\Services\WorldCupKnockoutGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 6 de la revisión de medios (grupo 05): los slots no resolubles se
 * descartaban en silencio (`if ($homeTeamId && $awayTeamId)` sin else) y el
 * torneo podía terminar sin campeón sin ningún error visible. Ahora se
 * registra el error y se lanza una excepción controlada.
 *
 * El bracket WEURO (data/2026/WEURO/bracket.json) abre las eliminatorias en
 * cuartos con slots "1A v 2B" y "1B v 2A" resueltos desde la fase de grupos.
 */
class WorldCupKnockoutSlotFixTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    /** @var array<string, Team> */
    private array $teams = [];

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->create([
            'id' => 'WEURO',
            'country' => 'EU',
            'handler_type' => 'group_stage_cup',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'EU',
            'team_id' => ($t = Team::factory()->create(['country' => 'EU']))->id,
            'competition_id' => 'WEURO',
            'current_date' => Carbon::parse('2029-07-01'),
        ]);

        foreach (['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D1', 'D2'] as $key) {
            $this->teams[$key] = Team::factory()->create(['country' => 'EU', 'name' => "Team $key"]);
        }
    }

    public function test_unresolvable_bracket_slot_throws_instead_of_shrinking(): void
    {
        // Falta el 2B: el slot "2B" del partido 1 no se puede resolver.
        foreach ([['A1', 1, 'A'], ['A2', 2, 'A'], ['B1', 1, 'B'], ['C1', 1, 'C'], ['C2', 2, 'C'], ['D1', 1, 'D'], ['D2', 2, 'D']] as [$key, $pos, $group]) {
            $this->standing($key, $pos, $group);
        }

        $this->expectException(UnresolvableBracketSlotException::class);
        $this->expectExceptionMessageMatches('/WEURO.*match 1/s');

        app(WorldCupKnockoutGenerator::class)->generateMatchups(
            $this->game,
            'WEURO',
            WorldCupKnockoutGenerator::ROUND_QUARTER_FINALS
        );
    }

    public function test_fully_resolvable_bracket_generates_every_matchup(): void
    {
        foreach ([['A1', 1, 'A'], ['A2', 2, 'A'], ['B1', 1, 'B'], ['B2', 2, 'B'], ['C1', 1, 'C'], ['C2', 2, 'C'], ['D1', 1, 'D'], ['D2', 2, 'D']] as [$key, $pos, $group]) {
            $this->standing($key, $pos, $group);
        }

        $matchups = app(WorldCupKnockoutGenerator::class)->generateMatchups(
            $this->game,
            'WEURO',
            WorldCupKnockoutGenerator::ROUND_QUARTER_FINALS
        );

        $this->assertCount(4, $matchups);
        // 1A v 2B, 1B v 2A, 1C v 2D, 1D v 2C según el bracket.
        $expected = [
            ['A1', 'B2'],
            ['B1', 'A2'],
            ['C1', 'D2'],
            ['D1', 'C2'],
        ];
        foreach ($expected as $i => [$home, $away]) {
            $this->assertSame(
                [$this->teams[$home]->id, $this->teams[$away]->id],
                [$matchups[$i][0], $matchups[$i][1]],
                "matchup $i"
            );
        }
    }

    private function standing(string $teamKey, int $position, string $group): void
    {
        GameStanding::create([
            'game_id' => $this->game->id,
            'competition_id' => 'WEURO',
            'group_label' => $group,
            'team_id' => $this->teams[$teamKey]->id,
            'position' => $position,
            'played' => 3,
            'points' => 9 - $position,
        ]);
    }
}
