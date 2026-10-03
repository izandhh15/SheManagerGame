<?php

namespace Tests\Feature\QaCriticalFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Playoffs\ESP2PlayoffGenerator;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Match\Handlers\LeagueWithPlayoffHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C4 regression test — ESP2 promotion playoff knockout config.
 *
 * Bug: ESP2PlayoffGenerator::getRoundConfig() threw
 * RuntimeException("No knockout round config found for ESP2 round 1")
 * because data/2026/ESP2/schedule.json had no `knockout` section → 500
 * when the ESP2 regular season ended. Fixed by adding the knockout
 * section (semifinal double-leg 2027-05-16/23, final double-leg
 * 2027-05-30/2027-06-06).
 *
 * Ported from the QA repro (agent-24): test_esp2_knockout_schedule_exists
 * and test_esp2_playoff_round_1_generates_after_regular_season.
 *
 * Note on seeding: the factory builds the generator from
 * config/countries.php (direct_count=1, playoff_count=4), so the
 * bracket is positions 2-5: [5v2], [4v3], lower seed hosts leg 1.
 */
class C4Esp2PlayoffTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    /** @var array<int, Team> 1-indexed by standings position */
    private array $teams = [];

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create([
            'id' => 'ESP2', 'tier' => 2, 'handler_type' => 'league_with_playoff',
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP2',
            'season' => '2026',
            'base_season' => '2026',
        ]);

        // 14 teams with fixed standings positions 1..14.
        for ($i = 1; $i <= 14; $i++) {
            $t = Team::factory()->create();
            $this->teams[$i] = $t;
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $t->id,
                'entry_round' => 1,
            ]);
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $t->id,
                'position' => $i,
                'played' => 26,
                'won' => max(0, 16 - $i),
                'drawn' => 4,
                'lost' => min(26, $i),
                'goals_for' => 60 - $i,
                'goals_against' => 20 + $i,
                'points' => max(0, 16 - $i) * 3 + 4,
            ]);
        }

        // Regular season is "complete": no unplayed league matches remain
        // (zero GameMatch rows also satisfies shouldGeneratePlayoffRound).
    }

    public function test_esp2_knockout_schedule_exists(): void
    {
        // The generator reads round configs from data/<base_season>/ESP2/schedule.json.
        $path = base_path('data/2026/ESP2/schedule.json');
        $data = json_decode(file_get_contents($path), true);
        $knockout = $data['knockout'] ?? [];
        $this->assertNotEmpty($knockout, 'ESP2 schedule.json must define knockout rounds for the playoff');

        // Round 1 = semifinal, round 2 = final, both double-legged.
        $rounds = collect($knockout)->keyBy('round');
        $this->assertTrue($rounds->has(1), 'Knockout round 1 (semifinal) must be configured');
        $this->assertTrue($rounds->has(2), 'Knockout round 2 (final) must be configured');
        foreach ([1, 2] as $round) {
            $this->assertNotEmpty($rounds[$round]['first_leg_date'] ?? null, "Round {$round} needs a first leg date");
            $this->assertNotEmpty($rounds[$round]['second_leg_date'] ?? null, "Round {$round} needs a second leg date");
        }
    }

    public function test_esp2_playoff_round_1_generates_after_regular_season(): void
    {
        $handler = app(LeagueWithPlayoffHandler::class);

        // This is the exact call MatchdayService makes when the player
        // advances past the last regular-season matchday. Before the fix
        // it threw RuntimeException: No knockout round config found for
        // ESP2 round 1.
        $handler->beforeMatches($this->game, '2026-06-01');

        $ties = CupTie::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP2')
            ->where('round_number', 1)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $ties, 'Playoff semifinal round should generate 2 ties');

        // Semifinal seeding with direct_count=1: positions 2-5 qualify,
        // highest vs lowest, 2nd-highest vs 2nd-lowest, lower seed hosts
        // the first leg: [pos5 vs pos2], [pos4 vs pos3].
        $this->assertEquals($this->teams[5]->id, $ties[0]->home_team_id);
        $this->assertEquals($this->teams[2]->id, $ties[0]->away_team_id);
        $this->assertEquals($this->teams[4]->id, $ties[1]->home_team_id);
        $this->assertEquals($this->teams[3]->id, $ties[1]->away_team_id);

        // Two legs per tie.
        foreach ($ties as $tie) {
            $this->assertNotNull($tie->first_leg_match_id);
            $this->assertNotNull($tie->second_leg_match_id);
        }

        // The generated legs must be dated from the knockout config.
        $this->assertEquals('2027-05-16', $ties[0]->firstLegMatch->scheduled_date->format('Y-m-d'));
        $this->assertEquals('2027-05-23', $ties[0]->secondLegMatch->scheduled_date->format('Y-m-d'));
    }

    public function test_esp2_generator_is_registered_in_factory(): void
    {
        $generator = app(PlayoffGeneratorFactory::class)->forCompetition('ESP2');
        $this->assertInstanceOf(ESP2PlayoffGenerator::class, $generator);
        $this->assertSame('ESP2', $generator->getCompetitionId());
    }
}
