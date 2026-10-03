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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UCLQ losers drop into UELQ round 2 (the real UWCL -> UWEC drop-down,
 * e.g. Juventus out in qualifying plays the Europa Cup from the second
 * qualifying round), while UCLQ winners reach the UWCL league phase.
 *
 * (The basic routing is also covered by RouteQualifyingResultsTest; this
 * class pins the UCLQ -> UELQ drop-down contract specifically, including
 * entry_round values and accumulation of several losers.)
 */
class UclqLosersToUelqTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->create(['id' => 'UCLQ', 'handler_type' => 'knockout_cup']);
        Competition::factory()->create(['id' => 'UCL', 'handler_type' => 'swiss_format']);
        Competition::factory()->create(['id' => 'UELQ', 'handler_type' => 'knockout_cup']);

        $userTeam = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'team_id' => $userTeam->id,
            'season' => '2026',
            'base_season' => '2026',
        ]);
    }

    private function fireUclqTie(string $winnerId, string $loserId, int $round = 1): void
    {
        $tie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'UCLQ',
            'round_number' => $round,
            'home_team_id' => $winnerId,
            'away_team_id' => $loserId,
            'winner_id' => $winnerId,
            'completed' => true,
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'UCLQ',
            'home_team_id' => $winnerId,
            'away_team_id' => $loserId,
        ]);

        foreach ([$winnerId, $loserId] as $teamId) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'UCLQ',
                'team_id' => $teamId,
                'entry_round' => 1,
            ]);
        }

        $event = new CupTieResolved(
            cupTie: $tie,
            winnerId: $winnerId,
            match: $match,
            game: $this->game,
            competition: Competition::where('id', 'UCLQ')->first(),
        );

        app(RouteQualifyingResultsListener::class)->handle($event);
    }

    private function entryRound(string $competitionId, string $teamId): ?int
    {
        return CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', $competitionId)
            ->where('team_id', $teamId)
            ->value('entry_round');
    }

    private function entries(string $competitionId): array
    {
        return CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->all();
    }

    public function test_uclq_loser_drops_to_uelq_round_2_winner_goes_to_ucl(): void
    {
        $winner = Team::factory()->create(['country' => 'IT']);
        $loser = Team::factory()->create(['country' => 'ES']);

        $this->fireUclqTie($winner->id, $loser->id);

        // Winner reaches the UWCL league phase.
        $this->assertContains($winner->id, $this->entries('UCL'));
        $this->assertSame(1, $this->entryRound('UCL', $winner->id));

        // Loser drops into the Europa Cup qualifying playoff at round 2.
        $this->assertContains($loser->id, $this->entries('UELQ'));
        $this->assertSame(2, $this->entryRound('UELQ', $loser->id));

        // Neither stays in UCLQ.
        $this->assertNotContains($winner->id, $this->entries('UCLQ'));
        $this->assertNotContains($loser->id, $this->entries('UCLQ'));
    }

    public function test_several_uclq_losers_accumulate_in_uelq_round_2(): void
    {
        $losers = [];

        for ($i = 0; $i < 3; $i++) {
            $winner = Team::factory()->create(['country' => 'IT']);
            $loser = Team::factory()->create(['country' => 'ES']);
            $losers[] = $loser->id;

            $this->fireUclqTie($winner->id, $loser->id);
        }

        foreach ($losers as $loserId) {
            $this->assertContains($loserId, $this->entries('UELQ'));
            $this->assertSame(2, $this->entryRound('UELQ', $loserId));
        }

        $this->assertSame(
            3,
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UELQ')
                ->where('entry_round', 2)
                ->count()
        );
    }

    public function test_routing_is_idempotent(): void
    {
        $winner = Team::factory()->create(['country' => 'IT']);
        $loser = Team::factory()->create(['country' => 'ES']);

        $this->fireUclqTie($winner->id, $loser->id);
        $this->fireUclqTie($winner->id, $loser->id);

        $this->assertCount(1, array_filter(
            $this->entries('UELQ'),
            fn ($id) => $id === $loser->id
        ));
        $this->assertCount(1, array_filter(
            $this->entries('UCL'),
            fn ($id) => $id === $winner->id
        ));
    }
}
