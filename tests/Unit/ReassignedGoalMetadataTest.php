<?php

namespace Tests\Unit;

use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * BAJA review: MatchSimulator::reassignEventsFromUnavailablePlayers()
 * rebuilt reassigned goals with MatchEventData::goal($teamId, $id, $min),
 * dropping the original metadata — a reassigned penalty goal lost its
 * is_penalty tag. Only the scorer may change, not how the goal was scored.
 */
class ReassignedGoalMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassigned_penalty_goal_keeps_is_penalty(): void
    {
        $home = Team::factory()->create(['name' => 'Home FC']);
        $away = Team::factory()->create(['name' => 'Away FC']);

        $scorer = $this->makePlayer($home, 'Centre-Forward');
        $subIn = $this->makePlayer($home, 'Central Midfield');
        $otherForward = $this->makePlayer($home, 'Centre-Forward');
        $awayPlayer = $this->makePlayer($away, 'Centre-Back');

        $events = new Collection([
            // Scorer is subbed off at 60'.
            MatchEventData::substitution($home->id, $scorer->id, $subIn->id, 60),
            // ...but "scores" a penalty at 70' — must be reassigned.
            new MatchEventData($home->id, $scorer->id, 70, 'goal', ['is_penalty' => true]),
        ]);

        $result = $this->reassign($events, $home, $away);

        $goal = $result->first(fn (MatchEventData $e) => $e->type === 'goal');
        $this->assertNotNull($goal);
        $this->assertNotSame($scorer->id, $goal->gamePlayerId, 'goal must be reassigned away from the subbed-off scorer');
        $this->assertTrue(
            $goal->metadata['is_penalty'] ?? false,
            'reassigned goal must preserve the is_penalty tag'
        );
    }

    public function test_reassigned_open_play_goal_keeps_its_metadata_shape(): void
    {
        $home = Team::factory()->create(['name' => 'Home FC']);
        $away = Team::factory()->create(['name' => 'Away FC']);

        $scorer = $this->makePlayer($home, 'Centre-Forward');
        $subIn = $this->makePlayer($home, 'Central Midfield');
        $otherForward = $this->makePlayer($home, 'Centre-Forward');

        $events = new Collection([
            MatchEventData::substitution($home->id, $scorer->id, $subIn->id, 60),
            new MatchEventData($home->id, $scorer->id, 70, 'goal', ['is_penalty' => false, 'x' => 1]),
        ]);

        $result = $this->reassign($events, $home, $away);

        $goal = $result->first(fn (MatchEventData $e) => $e->type === 'goal');
        $this->assertNotNull($goal);
        $this->assertSame(['is_penalty' => false, 'x' => 1], $goal->metadata);
    }

    private function makePlayer(Team $team, string $position): GamePlayer
    {
        return GamePlayer::factory()->create([
            'team_id' => $team->id,
            'position' => $position,
            'date_of_birth' => '1998-06-15',
        ]);
    }

    /**
     * @return Collection<int, MatchEventData>
     */
    private function reassign(Collection $events, Team $home, Team $away): Collection
    {
        $service = app(MatchSimulator::class);
        $method = new \ReflectionMethod($service, 'reassignEventsFromUnavailablePlayers');
        $method->setAccessible(true);

        $homePlayers = GamePlayer::where('team_id', $home->id)->get();
        $awayPlayers = GamePlayer::where('team_id', $away->id)->get();

        return $method->invoke($service, $events, $homePlayers, $awayPlayers, $home->id, $away->id);
    }
}
