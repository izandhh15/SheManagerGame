<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Modules\Match\Enums\MatchPhase;
use App\Modules\Match\Services\MatchResimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: MatchResimulationService::formatMatchEvents() paired
 * assists to goals with keyBy(phase:minute:stoppage:team), so when two
 * goals shared the same "90+2'" the key collided and BOTH goals were
 * credited with the LAST assist. Pairing is now deterministic: each goal
 * consumes the earliest still-unpaired assist for its key (by event id).
 */
class AssistGoalPairingTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_goals_sharing_a_stoppage_minute_each_get_their_own_assist(): void
    {
        $team = Team::factory()->create(['name' => 'Home FC']);
        Competition::factory()->league()->create(['id' => 'ESP1']);
        $game = Game::factory()->create([
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);
        $match = GameMatch::factory()->forGame($game)->create([
            'home_team_id' => $team->id,
            'competition_id' => 'ESP1',
        ]);

        [$scorer1, $assister1] = $this->makePlayers($game, $team, 'Scorer One', 'Assister One');
        [$scorer2, $assister2] = $this->makePlayers($game, $team, 'Scorer Two', 'Assister Two');

        // Two goals + two assists, all at 90+2' for the same team.
        $events = collect([
            $this->makeEvent($game, $match, $team, $scorer1, 'goal'),
            $this->makeEvent($game, $match, $team, $assister1, 'assist'),
            $this->makeEvent($game, $match, $team, $scorer2, 'goal'),
            $this->makeEvent($game, $match, $team, $assister2, 'assist'),
        ]);

        $formatted = MatchResimulationService::formatMatchEvents($events);

        $goals = array_values(array_filter($formatted, fn ($e) => $e['type'] === 'goal'));
        $this->assertCount(2, $goals);

        $byScorer = [];
        foreach ($goals as $goal) {
            $byScorer[$goal['playerName']] = $goal['assistPlayerId'] ?? null;
        }

        // Before the fix both goals carried Assister Two's id (keyBy kept
        // the last assist). Now each goal has a DIFFERENT assist.
        $this->assertNotNull($byScorer['Scorer One']);
        $this->assertNotNull($byScorer['Scorer Two']);
        $this->assertNotSame(
            $byScorer['Scorer One'],
            $byScorer['Scorer Two'],
            'the two 90+2\' goals must not share the same assist'
        );
        $this->assertEqualsCanonicalizing(
            [$assister1->id, $assister2->id],
            [$byScorer['Scorer One'], $byScorer['Scorer Two']],
            'both assists must be consumed exactly once'
        );
    }

    public function test_single_goal_still_pairs_with_its_assist(): void
    {
        $team = Team::factory()->create(['name' => 'Home FC']);
        Competition::factory()->league()->create(['id' => 'ESP1']);
        $game = Game::factory()->create([
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);
        $match = GameMatch::factory()->forGame($game)->create([
            'home_team_id' => $team->id,
            'competition_id' => 'ESP1',
        ]);

        [$scorer, $assister] = $this->makePlayers($game, $team, 'Scorer', 'Assister');

        $events = collect([
            $this->makeEvent($game, $match, $team, $scorer, 'goal'),
            $this->makeEvent($game, $match, $team, $assister, 'assist'),
        ]);

        $formatted = MatchResimulationService::formatMatchEvents($events);
        $goals = array_values(array_filter($formatted, fn ($e) => $e['type'] === 'goal'));

        $this->assertCount(1, $goals);
        $this->assertSame($assister->id, $goals[0]['assistPlayerId']);
        $this->assertSame('Assister', $goals[0]['assistPlayerName']);
    }

    /**
     * @return array{GamePlayer, GamePlayer}
     */
    private function makePlayers(Game $game, Team $team, string $scorerName, string $assisterName): array
    {
        $mk = fn (string $name) => GamePlayer::factory()
            ->forGame($game)
            ->forTeam($team)
            ->create(['name' => $name, 'date_of_birth' => '1998-06-15']);

        return [$mk($scorerName), $mk($assisterName)];
    }

    private function makeEvent(Game $game, GameMatch $match, Team $team, GamePlayer $player, string $type): MatchEvent
    {
        return MatchEvent::create([
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'game_player_id' => $player->id,
            'team_id' => $team->id,
            'minute' => 90,
            'phase' => MatchPhase::SECOND_HALF,
            'stoppage_minute' => 2,
            'event_type' => $type,
            'metadata' => [],
        ]);
    }
}
