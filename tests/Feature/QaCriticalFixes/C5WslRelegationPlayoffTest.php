<?php

namespace Tests\Feature\QaCriticalFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Playoffs\WSLRelegationPlayoffGenerator;
use App\Modules\Match\Handlers\LeagueWithPlayoffHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C5 regression test — WSL relegation playoff (ENGPO).
 *
 * Bug: WSLRelegationPlayoffGenerator::simulatedOrder() referenced the
 * non-existent class App\Modules\Season\Services\SeasonSimulationService,
 * throwing ReflectionException (500) when the ENG1 13th vs ENG2 2nd
 * playoff was generated after the regular season. The other league is
 * lazily simulated via App\Modules\Finance\Services\SeasonSimulationService
 * since the game never simulates leagues the player isn't in.
 *
 * Ported from the QA repro (agent-24): test_engpo_generates_through_handler_before_matches
 * and test_engpo_matchup_generates_after_regular_season.
 */
class C5WslRelegationPlayoffTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create([
            'id' => 'ENG1', 'tier' => 1, 'handler_type' => 'league_with_playoff',
        ]);
        Competition::factory()->league()->create([
            'id' => 'ENG2', 'tier' => 2, 'handler_type' => 'league_with_playoff',
        ]);
        Competition::factory()->create([
            'id' => 'ENGPO', 'tier' => 1, 'handler_type' => 'knockout_cup',
            'name' => 'WSL Relegation Playoff',
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create();
        // Player manages an ENG1 side.
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ENG1',
            'season' => '2026',
            'base_season' => '2026',
        ]);

        // ENG1: 14 teams, real standings (player's league).
        for ($i = 1; $i <= 14; $i++) {
            $t = Team::factory()->create();
            CompetitionEntry::create([
                'game_id' => $this->game->id, 'competition_id' => 'ENG1',
                'team_id' => $t->id, 'entry_round' => 1,
            ]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ENG1',
                'team_id' => $t->id, 'position' => $i, 'played' => 26,
                'points' => (15 - $i) * 5,
            ]);
        }

        // ENG2: 12 teams entered, but NO standings and NO SimulatedSeason —
        // the game never simulates the league the player isn't in, and the
        // season-closing pipeline hasn't run yet at playoff time.
        for ($i = 1; $i <= 12; $i++) {
            CompetitionEntry::create([
                'game_id' => $this->game->id, 'competition_id' => 'ENG2',
                'team_id' => Team::factory()->create()->id, 'entry_round' => 1,
            ]);
        }
    }

    public function test_engpo_matchup_generates_after_regular_season(): void
    {
        $generator = new WSLRelegationPlayoffGenerator();

        // Must not throw ReflectionException; returns [[ENG2 2nd (hosts), ENG1 13th]].
        $matchups = $generator->generateMatchups($this->game, 1);

        $this->assertCount(1, $matchups);
        $this->assertCount(2, $matchups[0]);

        $eng1Pos13 = GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', 'ENG1')
            ->where('position', 13)
            ->value('team_id');
        $this->assertEquals($eng1Pos13, $matchups[0][1], 'ENG1 13th must be the away side');

        $eng2Teams = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'ENG2')
            ->pluck('team_id')
            ->all();
        $this->assertContains($matchups[0][0], $eng2Teams, 'ENG2 participant must come from ENG2 entries');
    }

    public function test_engpo_generates_through_handler_before_matches(): void
    {
        // Production path: MatchdayService -> generatePendingMatches ->
        // LeagueWithPlayoffHandler::beforeMatches.
        $handler = app(LeagueWithPlayoffHandler::class);
        $handler->beforeMatches($this->game, '2026-05-30');

        $ties = CupTie::where('game_id', $this->game->id)
            ->where('competition_id', 'ENGPO')
            ->where('round_number', 1)
            ->get();

        $this->assertCount(1, $ties, 'ENGPO single-match playoff should generate exactly 1 tie');
        $this->assertNotNull($ties[0]->first_leg_match_id);
        $this->assertNull($ties[0]->second_leg_match_id, 'ENGPO is a single match, no second leg');
    }
}
