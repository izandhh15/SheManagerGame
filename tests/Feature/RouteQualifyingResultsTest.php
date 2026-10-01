<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Listeners\RouteQualifyingResultsListener;
use App\Modules\Season\Services\SeasonInitializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UEFA qualifying playoff routing: UCLQ winners reach the UWCL league
 * phase, UCLQ losers drop to the Europa Cup (the Juventus scenario);
 * UELQ winners reach the Europa Cup, losers are out.
 */
class RouteQualifyingResultsTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $winner;
    private Team $loser;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->create(['id' => 'UCLQ', 'handler_type' => 'knockout_cup']);
        Competition::factory()->create(['id' => 'UCL', 'handler_type' => 'swiss_format']);
        Competition::factory()->create(['id' => 'UEL', 'handler_type' => 'swiss_format']);
        Competition::factory()->create(['id' => 'UELQ', 'handler_type' => 'knockout_cup']);

        $userTeam = Team::factory()->create(['country' => 'ES']);
        $this->winner = Team::factory()->create(['country' => 'IT']);
        $this->loser = Team::factory()->create(['country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'team_id' => $userTeam->id,
            'season' => '2026',
            'base_season' => '2026',
        ]);
    }

    private function fireListener(string $competitionId, string $winnerId, string $loserId): void
    {
        $tie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'home_team_id' => $winnerId,
            'away_team_id' => $loserId,
            'winner_id' => $winnerId,
            'completed' => true,
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'home_team_id' => $winnerId,
            'away_team_id' => $loserId,
        ]);

        // Both start in the qualifying competition
        foreach ([$winnerId, $loserId] as $teamId) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $teamId,
                'entry_round' => 1,
            ]);
        }

        $competition = Competition::where('id', $competitionId)->first();

        $event = new CupTieResolved(
            cupTie: $tie,
            winnerId: $winnerId,
            match: $match,
            game: $this->game,
            competition: $competition,
        );

        $listener = app(RouteQualifyingResultsListener::class);
        $listener->handle($event);
    }

    private function entries(string $competitionId): array
    {
        return CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->all();
    }

    public function test_uclq_winner_goes_to_ucl_loser_drops_to_europa(): void
    {
        $this->fireListener('UCLQ', $this->winner->id, $this->loser->id);

        $this->assertContains($this->winner->id, $this->entries('UCL'));
        $this->assertContains($this->loser->id, $this->entries('UEL'));
        $this->assertNotContains($this->winner->id, $this->entries('UCLQ'));
        $this->assertNotContains($this->loser->id, $this->entries('UCLQ'));
    }

    public function test_uelq_winner_goes_to_europa_loser_is_out(): void
    {
        $this->fireListener('UELQ', $this->winner->id, $this->loser->id);

        $this->assertContains($this->winner->id, $this->entries('UEL'));
        $this->assertNotContains($this->loser->id, $this->entries('UEL'));
        $this->assertNotContains($this->winner->id, $this->entries('UELQ'));
    }

    public function test_routing_is_idempotent(): void
    {
        $this->fireListener('UCLQ', $this->winner->id, $this->loser->id);
        $this->fireListener('UCLQ', $this->winner->id, $this->loser->id);

        $this->assertCount(1, array_filter(
            $this->entries('UCL'),
            fn ($id) => $id === $this->winner->id
        ));
    }
}
