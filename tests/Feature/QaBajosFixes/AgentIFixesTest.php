<?php

namespace Tests\Feature\QaBajosFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Report\Services\AwardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 4 (bugs bajos) — Agente I: B24, B25, B26.
 *
 * B24: StandingsCalculator::recalculatePositions() had no fourth tiebreak:
 * a total tie (points, goal difference, goals for) on a promotion /
 * relegation cut was decided by Postgres' physical row order. The fix adds
 * team_id ASC as a deterministic final criterion.
 *
 * B25: AwardService::getTopScorers() had no final tiebreak: a perfect tie
 * (goals, assists, appearances) crowned an arbitrary winner. The fix adds
 * game_players.id ASC as a deterministic final criterion.
 *
 * B26: the pichichi counted goals from ALL competitions because the stats
 * live aggregated per season in game_player_match_state. The fix adds an
 * optional $competitionId to getTopScorers() that counts goals/assists from
 * the competition's match events instead (same approach as the MVP's
 * competition_id filtering and CompetitionViewService::getTopScorers()).
 */
class AgentIFixesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pin to pgsql: the suite's migrations use postgres-only DDL, so this
     * test cannot run on sqlite regardless of the base TestCase setting.
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = ['pgsql'];

    // ======================================================================
    // B24 — deterministic final tiebreak in recalculatePositions()
    // ======================================================================

    public function test_b24_full_tie_orders_by_team_id_asc_not_physical_row_order(): void
    {
        $user = User::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
            'season' => '2025',
        ]);

        // Explicit ids so the expected order is known: team_id ASC.
        // Inserted in REVERSE physical order to prove the result does not
        // follow Postgres' row order.
        $teamIds = [
            '00000000-0000-0000-0000-0000000000c3',
            '00000000-0000-0000-0000-0000000000c2',
            '00000000-0000-0000-0000-0000000000c1',
        ];
        foreach ($teamIds as $teamId) {
            $team = Team::factory()->create(['id' => $teamId, 'name' => 'Team ' . $teamId]);
            GameStanding::create([
                'game_id' => $game->id,
                'competition_id' => $competition->id,
                'team_id' => $team->id,
                'position' => 0,
                'played' => 20,
                'won' => 10, 'drawn' => 5, 'lost' => 5,
                'goals_for' => 40, 'goals_against' => 20,
                'points' => 35,
            ]);
        }

        $calculator = new StandingsCalculator;
        $calculator->recalculatePositions($game->id, $competition->id);
        $firstRun = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->orderBy('position')
            ->pluck('team_id')
            ->all();

        // Total tie → team_id ASC wins, not the reverse physical insert order.
        $this->assertSame(
            [
                '00000000-0000-0000-0000-0000000000c1',
                '00000000-0000-0000-0000-0000000000c2',
                '00000000-0000-0000-0000-0000000000c3',
            ],
            $firstRun
        );

        // Stable between executions.
        $calculator->recalculatePositions($game->id, $competition->id);
        $secondRun = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->orderBy('position')
            ->pluck('team_id')
            ->all();

        $this->assertSame($firstRun, $secondRun);

        // Positions stay contiguous 1..3.
        $positions = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->orderBy('position')
            ->pluck('position')
            ->all();
        $this->assertSame([1, 2, 3], $positions);
    }

    // ======================================================================
    // B25 — deterministic final tiebreak in getTopScorers()
    // ======================================================================

    public function test_b25_identical_forwards_yield_stable_winner_by_lowest_player_id(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Valencia Stars']);
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
            'season' => '2025',
        ]);

        // Identical stats; explicit ids so the lower one must win.
        $first = GamePlayer::factory()->create([
            'id' => '00000000-0000-0000-0000-0000000000a1',
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Delantera A',
            'position' => 'Striker',
            'goals' => 20, 'assists' => 5, 'appearances' => 25,
        ]);
        $second = GamePlayer::factory()->create([
            'id' => '00000000-0000-0000-0000-0000000000a2',
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => 'Delantera B',
            'position' => 'Striker',
            'goals' => 20, 'assists' => 5, 'appearances' => 25,
        ]);

        $service = new AwardService;

        $winnerOne = $service->getTopScorers($game->id, limit: 2)->first();
        $winnerTwo = $service->getTopScorers($game->id, limit: 2)->first();

        // Same winner across executions (no DB-engine coin flip)…
        $this->assertSame($winnerOne->id, $winnerTwo->id);
        // …and it is the deterministic one: lowest player id.
        $this->assertSame($first->id, $winnerOne->id);
        $this->assertNotSame($second->id, $winnerOne->id);
    }

    // ======================================================================
    // B26 — pichichi counts league goals only when $competitionId is given
    // ======================================================================

    public function test_b26_pichichi_filters_goals_by_league_competition(): void
    {
        $user = User::factory()->create();
        $teamOne = Team::factory()->create(['name' => 'Valencia Stars']);
        $teamTwo = Team::factory()->create(['name' => 'Madrid Rivals']);
        $league = Competition::factory()->league()->create();
        $cup = Competition::factory()->create();
        $game = Game::factory()->forTeam($teamOne)->create([
            'user_id' => $user->id,
            'competition_id' => $league->id,
            'season' => '2025',
        ]);

        foreach ([$teamOne, $teamTwo] as $team) {
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => $league->id,
                'team_id' => $team->id,
            ]);
        }

        // Striker A: 15 season goals in the state table, but only 5 in the
        // league — 10 came in the cup. Striker B: 6 goals, all in the league.
        $strikerA = GamePlayer::factory()->create([
            'game_id' => $game->id, 'team_id' => $teamOne->id,
            'name' => 'Delantera Copera', 'position' => 'Striker',
            'goals' => 15, 'assists' => 2, 'appearances' => 30,
        ]);
        $strikerB = GamePlayer::factory()->create([
            'game_id' => $game->id, 'team_id' => $teamTwo->id,
            'name' => 'Delantera Ligera', 'position' => 'Striker',
            'goals' => 6, 'assists' => 1, 'appearances' => 28,
        ]);

        $leagueMatch = GameMatch::factory()->forGame($game)->create([
            'competition_id' => $league->id,
            'home_team_id' => $teamOne->id,
            'away_team_id' => $teamTwo->id,
            'played' => true, 'home_score' => 5, 'away_score' => 6,
        ]);
        $cupMatch = GameMatch::factory()->forGame($game)->create([
            'competition_id' => $cup->id,
            'home_team_id' => $teamOne->id,
            'away_team_id' => $teamTwo->id,
            'played' => true, 'home_score' => 10, 'away_score' => 0,
        ]);

        $this->scoreGoals($game, $leagueMatch, $strikerA, $teamOne, 5);
        $this->scoreGoals($game, $cupMatch, $strikerA, $teamOne, 10);
        $this->scoreGoals($game, $leagueMatch, $strikerB, $teamTwo, 6);

        $service = new AwardService;
        $teamIds = collect([$teamOne->id, $teamTwo->id]);

        // Without a competition filter the season-wide aggregate still wins
        // for the cup-heavy striker (documented legacy scope).
        $unfiltered = $service->getTopScorers($game->id, $teamIds, limit: 2);
        $this->assertSame($strikerA->id, $unfiltered->first()->id);

        // League-scoped: the 6 league goals beat the 5 league goals, even
        // though the rival has 15 across all competitions.
        $leagueScorers = $service->getTopScorers(
            $game->id, $teamIds, limit: 2, competitionId: $league->id
        );
        $this->assertSame($strikerB->id, $leagueScorers->first()->id);
        $this->assertSame(6, $leagueScorers->first()->goals);
        $this->assertSame(5, $leagueScorers->get(1)->goals);

        // Cup-scoped: the cup-heavy striker leads with her 10 cup goals.
        $cupScorers = $service->getTopScorers(
            $game->id, $teamIds, limit: 2, competitionId: $cup->id
        );
        $this->assertSame($strikerA->id, $cupScorers->first()->id);
        $this->assertSame(10, $cupScorers->first()->goals);
    }

    public function test_b26_competition_tiebreak_is_deterministic(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Valencia Stars']);
        $league = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'competition_id' => $league->id,
            'season' => '2025',
        ]);
        CompetitionEntry::create([
            'game_id' => $game->id,
            'competition_id' => $league->id,
            'team_id' => $team->id,
        ]);

        $first = GamePlayer::factory()->create([
            'id' => '00000000-0000-0000-0000-0000000000d1',
            'game_id' => $game->id, 'team_id' => $team->id,
            'name' => 'Delantera D1', 'position' => 'Striker',
            'goals' => 4, 'assists' => 0, 'appearances' => 10,
        ]);
        $second = GamePlayer::factory()->create([
            'id' => '00000000-0000-0000-0000-0000000000d2',
            'game_id' => $game->id, 'team_id' => $team->id,
            'name' => 'Delantera D2', 'position' => 'Striker',
            'goals' => 4, 'assists' => 0, 'appearances' => 10,
        ]);

        $match = GameMatch::factory()->forGame($game)->create([
            'competition_id' => $league->id,
            'home_team_id' => $team->id,
            'away_team_id' => Team::factory()->create()->id,
            'played' => true, 'home_score' => 8, 'away_score' => 0,
        ]);
        // Identical league tallies: 4 goals each, no assists.
        $this->scoreGoals($game, $match, $first, $team, 4);
        $this->scoreGoals($game, $match, $second, $team, 4);

        $service = new AwardService;
        $teamIds = collect([$team->id]);

        $runOne = $service->getTopScorers($game->id, $teamIds, limit: 2, competitionId: $league->id);
        $runTwo = $service->getTopScorers($game->id, $teamIds, limit: 2, competitionId: $league->id);

        $this->assertSame(
            $runOne->pluck('id')->all(),
            $runTwo->pluck('id')->all()
        );
        $this->assertSame($first->id, $runOne->first()->id);
    }

    /**
     * Record $count goal events for a player in a match.
     */
    private function scoreGoals(Game $game, GameMatch $match, GamePlayer $player, Team $team, int $count): void
    {
        foreach (range(1, $count) as $i) {
            MatchEvent::create([
                'game_id' => $game->id,
                'game_match_id' => $match->id,
                'game_player_id' => $player->id,
                'team_id' => $team->id,
                'minute' => min($i * 5, 90),
                'event_type' => MatchEvent::TYPE_GOAL,
            ]);
        }
    }
}
