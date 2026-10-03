<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\AcademyPlayer;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Academy\Services\YouthAcademyService;
use App\Modules\Competition\Services\CompetitionViewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecPerfFixesTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // YouthAcademyService::developLoanedPlayers() — single UPDATE batch
    // ------------------------------------------------------------------

    public function test_develop_loaned_players_applies_growth_formula_in_batch(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $otherTeam = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-09-01',
        ]);

        $make = fn (string $teamId, bool $onLoan, int $overall, int $potential) => AcademyPlayer::create([
            'id' => (string) Str::uuid(),
            'game_id' => $game->id,
            'team_id' => $teamId,
            'name' => 'Canterana '.Str::random(6),
            'nationality' => ['Spain'],
            'date_of_birth' => Carbon::parse('2026-08-15')->subYears(17)->toDateString(),
            'position' => 'Central Midfield',
            'overall_score' => $overall,
            'potential' => $potential,
            'potential_low' => $potential - 5,
            'potential_high' => $potential + 5,
            'appeared_at' => '2026-08-15',
            'is_on_loan' => $onLoan,
            'is_jewel' => false,
            'joined_season' => 2026,
        ]);

        // growth = (80-60)*0.35 = 7 -> 67
        $loaned = $make($team->id, true, 60, 80);
        // capped at potential: (80-79)*0.35 = 0.35 -> round(79.35) = 79
        $capped = $make($team->id, true, 79, 80);
        // not on loan: untouched
        $staying = $make($team->id, false, 60, 80);
        // other team's loaned player: untouched
        $foreign = $make($otherTeam->id, true, 60, 80);

        app(YouthAcademyService::class)->developLoanedPlayers($game);

        $this->assertSame(67, (int) $loaned->refresh()->overall_score);
        $this->assertSame(79, (int) $capped->refresh()->overall_score);
        $this->assertSame(60, (int) $staying->refresh()->overall_score);
        $this->assertSame(60, (int) $foreign->refresh()->overall_score);
    }

    // ------------------------------------------------------------------
    // CompetitionViewService::backfillMissingForms() — bounded match read
    // ------------------------------------------------------------------

    public function test_backfill_missing_forms_computes_last_five_without_loading_history(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create(['country' => 'ES']);
        $teamB = Team::factory()->create(['country' => 'ES']);
        $competition = Competition::factory()->create([
            'id' => 'ESP1',
            'country' => 'ES',
            'tier' => 1,
            'role' => Competition::ROLE_LEAGUE,
            'handler_type' => 'league',
        ]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $teamA->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2026',
            'current_date' => '2026-09-01',
        ]);

        $standingA = GameStanding::create([
            'game_id' => $game->id, 'competition_id' => 'ESP1', 'team_id' => $teamA->id,
            'position' => 1, 'played' => 7, 'form' => null,
        ]);
        GameStanding::create([
            'game_id' => $game->id, 'competition_id' => 'ESP1', 'team_id' => $teamB->id,
            'position' => 2, 'played' => 7, 'form' => null,
        ]);

        // 7 matches, oldest first: W W L W D W W (last five: L W D W W)
        $results = [[3, 1], [2, 0], [0, 1], [1, 0], [2, 2], [4, 1], [1, 0]];
        foreach ($results as $i => [$home, $away]) {
            GameMatch::create([
                'id' => (string) Str::uuid(),
                'game_id' => $game->id,
                'competition_id' => 'ESP1',
                'round_number' => $i + 1,
                'home_team_id' => $teamA->id,
                'away_team_id' => $teamB->id,
                'scheduled_date' => Carbon::parse('2026-08-01')->addWeeks($i)->toDateString(),
                'home_score' => $home,
                'away_score' => $away,
                'played' => true,
            ]);
        }

        $service = app(CompetitionViewService::class);
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', 'ESP1')
            ->orderBy('position')
            ->get();

        $forms = $service->getTeamForms($standings);

        // getTeamForms() devuelve str_split($form): arrays de letras.
        $this->assertSame(['L', 'W', 'D', 'W', 'W'], $forms[$teamA->id]);
        $this->assertSame(['W', 'L', 'D', 'L', 'L'], $forms[$teamB->id]);
        // Persisted (self-healing backfill kept)
        $this->assertSame('LWDWW', $standingA->refresh()->form);
    }

    // ------------------------------------------------------------------
    // Leaderboards: appends() outside Cache::remember
    // ------------------------------------------------------------------

    public function test_leaderboard_pagination_links_use_current_request_query(): void
    {
        // Prime the cache with one set of query params...
        $this->get('/leaderboard?probe=first')->assertOk();

        // ...then request the same cache key with different params.
        $response = $this->get('/leaderboard?probe=second');
        $response->assertOk();

        $managers = $response->viewData('managers');
        $url = $managers->url(2);

        $this->assertStringContainsString('probe=second', $url);
        $this->assertStringNotContainsString('probe=first', $url);
    }

    public function test_tournament_leaderboard_pagination_links_use_current_request_query(): void
    {
        $this->get('/leaderboard/tournament?probe=first')->assertOk();

        $response = $this->get('/leaderboard/tournament?probe=second');
        $response->assertOk();

        $rankings = $response->viewData('rankings');
        $url = $rankings->url(2);

        $this->assertStringContainsString('probe=second', $url);
        $this->assertStringNotContainsString('probe=first', $url);
    }

    // ------------------------------------------------------------------
    // TrackVisitors: heartbeat throttled per visitor
    // ------------------------------------------------------------------

    public function test_visitor_heartbeat_is_throttled_within_window(): void
    {
        $this->get('/')->assertOk();

        $first = DB::table('visitor_heartbeats')->value('last_seen');
        $this->assertNotNull($first);

        // 1 minute later: still inside the 3-minute throttle -> no rewrite.
        $this->travel(1)->minutes();
        $this->get('/')->assertOk();
        $this->assertSame($first, DB::table('visitor_heartbeats')->value('last_seen'));

        // Past the throttle window -> heartbeat refreshes.
        $this->travel(3)->minutes();
        $this->get('/')->assertOk();
        $this->assertNotSame($first, DB::table('visitor_heartbeats')->value('last_seen'));
    }
}
