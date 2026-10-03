<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Listeners\RouteQualifyingResultsListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UELQ winner routing:
 * - round-1 winners STAY in UELQ (their entry already covers round 2);
 * - round-2 winners move to the Europa Cup (UEL);
 * - losers of any UELQ round are out (their entry is deleted).
 *
 * (The basic cases are also covered by RouteQualifyingResultsTest; this
 * class additionally pins that a round-1 winner still feeds the round-2
 * draw pool, i.e. staying in UELQ is not the same as being eliminated.)
 */
class UelqRoundWinnersRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->create(['id' => 'UCLQ', 'handler_type' => 'knockout_cup']);
        Competition::factory()->create(['id' => 'UCL', 'handler_type' => 'swiss_format']);
        Competition::factory()->create(['id' => 'UELQ', 'handler_type' => 'knockout_cup']);
        Competition::factory()->create(['id' => 'UEL', 'handler_type' => 'knockout_cup']);

        $userTeam = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'team_id' => $userTeam->id,
            'season' => '2026',
            'base_season' => '2026',
        ]);
    }

    private function fireUelqTie(string $winnerId, string $loserId, int $round): void
    {
        $tie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'UELQ',
            'round_number' => $round,
            'home_team_id' => $winnerId,
            'away_team_id' => $loserId,
            'winner_id' => $winnerId,
            'completed' => true,
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'UELQ',
            'home_team_id' => $winnerId,
            'away_team_id' => $loserId,
        ]);

        foreach ([$winnerId, $loserId] as $teamId) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'UELQ',
                'team_id' => $teamId,
                'entry_round' => 1,
            ]);
        }

        $event = new CupTieResolved(
            cupTie: $tie,
            winnerId: $winnerId,
            match: $match,
            game: $this->game,
            competition: Competition::where('id', 'UELQ')->first(),
        );

        app(RouteQualifyingResultsListener::class)->handle($event);
    }

    private function entries(string $competitionId): array
    {
        return CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->all();
    }

    public function test_round_1_winner_stays_in_uelq_and_feeds_round_2_draw(): void
    {
        $winner = Team::factory()->create(['country' => 'ES']);
        $loser = Team::factory()->create(['country' => 'PT']);

        $this->fireUelqTie($winner->id, $loser->id, 1);

        // The winner stays in UELQ and does NOT jump to the Europa Cup early.
        $this->assertContains($winner->id, $this->entries('UELQ'));
        $this->assertNotContains($winner->id, $this->entries('UEL'));

        // The loser is out of UELQ entirely.
        $this->assertNotContains($loser->id, $this->entries('UELQ'));

        // The winner still counts for the round-2 draw: it is a completed
        // round-1 tie winner, which is what CupDrawService pools for round 2.
        $r1Winners = CupTie::where('game_id', $this->game->id)
            ->where('competition_id', 'UELQ')
            ->where('round_number', 1)
            ->where('completed', true)
            ->whereNotNull('winner_id')
            ->pluck('winner_id')
            ->all();
        $this->assertContains($winner->id, $r1Winners);
    }

    public function test_round_2_winner_moves_to_uel(): void
    {
        $winner = Team::factory()->create(['country' => 'ES']);
        $loser = Team::factory()->create(['country' => 'PT']);

        $this->fireUelqTie($winner->id, $loser->id, 2);

        // Round-2 winners reach the Europa Cup knockout.
        $this->assertContains($winner->id, $this->entries('UEL'));
        $this->assertSame(
            1,
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $winner->id)
                ->value('entry_round')
        );

        // They leave UELQ behind, and the loser is out of both.
        $this->assertNotContains($winner->id, $this->entries('UELQ'));
        $this->assertNotContains($loser->id, $this->entries('UELQ'));
        $this->assertNotContains($loser->id, $this->entries('UEL'));
    }

    public function test_round_1_loser_is_out_not_routed_anywhere(): void
    {
        $winner = Team::factory()->create(['country' => 'ES']);
        $loser = Team::factory()->create(['country' => 'PT']);

        $this->fireUelqTie($winner->id, $loser->id, 1);

        foreach (['UELQ', 'UEL', 'UCL', 'UCLQ'] as $competitionId) {
            $this->assertNotContains(
                $loser->id,
                $this->entries($competitionId),
                "round-1 loser must not appear in {$competitionId}"
            );
        }
    }
}
