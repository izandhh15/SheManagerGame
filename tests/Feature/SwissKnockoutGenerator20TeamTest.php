<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\SwissKnockoutGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 20-team swiss format (UEL): positions 1-12 go straight to the Round of 16,
 * positions 13-20 contest the knockout playoff (4 ties -> 4 winners).
 * R16 = 12 direct + 4 playoff winners = 16 teams -> 8 ties.
 *
 * Regression: the generator was hardcoded to the 36-team UEFA format
 * (playoff positions 9-24), so a 20-team league phase died with
 * "Playoff generation failed: expected 8 matchups" (RuntimeException)
 * the moment the league phase completed.
 */
class SwissKnockoutGenerator20TeamTest extends TestCase
{
    use RefreshDatabase;

    private SwissKnockoutGenerator $generator;
    private Game $game;
    private Competition $competition;

    /** @var array<int, Team> league position (1..20) => Team */
    private array $teamsByPosition = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(SwissKnockoutGenerator::class);

        $this->competition = Competition::factory()->create([
            'id' => 'UEL',
            'handler_type' => 'swiss_format',
            'season' => '2025',
        ]);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create();

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'season' => '2025',
        ]);

        for ($pos = 1; $pos <= 20; $pos++) {
            $team = Team::factory()->create();
            $this->teamsByPosition[$pos] = $team;

            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->competition->id,
                'team_id' => $team->id,
                'position' => $pos,
                'played' => 6,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => max(0, 20 - $pos),
            ]);
        }
    }

    public function test_20_team_playoff_generates_four_ties_from_positions_13_to_20(): void
    {
        $matchups = $this->generator->generateMatchups($this->game, $this->competition->id, 1);

        $this->assertCount(4, $matchups);

        $seenPositions = [];
        $bracketCounts = [0 => 0, 1 => 0];
        $positionByTeam = array_flip(array_map(fn (Team $t) => $t->id, $this->teamsByPosition));

        foreach ($matchups as [$home, $away, $bracket]) {
            $this->assertNotNull($bracket);
            $this->assertContains($bracket, [0, 1]);
            $bracketCounts[$bracket]++;

            $seenPositions[] = $positionByTeam[$home];
            $seenPositions[] = $positionByTeam[$away];
        }

        $this->assertSame(2, $bracketCounts[0]);
        $this->assertSame(2, $bracketCounts[1]);

        sort($seenPositions);
        $this->assertSame(range(13, 20), $seenPositions, 'playoff must involve exactly positions 13-20');
    }

    public function test_20_team_r16_has_16_teams_12_direct_plus_4_playoff_winners(): void
    {
        $winners = [];
        foreach ($this->generator->generateMatchups($this->game, $this->competition->id, 1) as [$home, $away, $bracket]) {
            CupTie::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $this->game->id,
                'competition_id' => $this->competition->id,
                'round_number' => 1,
                'bracket_position' => $bracket,
                'home_team_id' => $home,
                'away_team_id' => $away,
                'winner_id' => $away, // higher seed wins
                'completed' => true,
            ]);
            $winners[] = $away;
        }

        $r16 = $this->generator->generateMatchups($this->game, $this->competition->id, 2);

        $this->assertCount(8, $r16);

        $r16Teams = [];
        foreach ($r16 as [$home, $away]) {
            $r16Teams[] = $home;
            $r16Teams[] = $away;
        }
        sort($r16Teams);

        $directIds = array_map(
            fn (int $pos) => $this->teamsByPosition[$pos]->id,
            range(1, 12)
        );
        sort($winners);
        $expected = array_merge($directIds, $winners);
        sort($expected);

        $this->assertSame($expected, $r16Teams, 'R16 must be the 12 direct seeds plus the 4 playoff winners');
    }

    public function test_unsupported_field_size_throws_clear_error(): void
    {
        // Remove 4 teams -> 16-team field, which has no bracket design.
        GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', $this->competition->id)
            ->whereIn('position', [17, 18, 19, 20])
            ->delete();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported swiss league-phase size: 16 teams');

        $this->generator->generateMatchups($this->game, $this->competition->id, 1);
    }
}
