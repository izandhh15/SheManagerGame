<?php

namespace Tests\Feature;

use App\Events\SeasonCompleted;
use App\Http\Actions\AdvanceSeasonTransition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Manager\Services\NationalTeamStatsService;
use App\Modules\Season\Listeners\SimulateOtherLeagues;
use App\Modules\Season\Services\GameCreationService;
use App\Modules\Season\Services\SeasonTransitionChunkService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Baja fase 5 — null-safety fixes (TRIAGE-BAJA familias null-safety).
 */
class ReviewBajaNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_age_returns_null_when_dob_is_null(): void
    {
        $player = new GamePlayer(['date_of_birth' => null]);

        $this->assertNull($player->age(now()));
    }

    public function test_age_still_computes_when_dob_present(): void
    {
        $player = new GamePlayer(['date_of_birth' => '2000-01-01']);

        $this->assertSame(26, $player->age(Carbon::parse('2026-06-01')));
    }

    public function test_development_status_is_neutral_peak_when_dob_is_null(): void
    {
        $player = new GamePlayer(['date_of_birth' => null]);

        // 'peak' keeps views that index $devLabels by status working.
        $this->assertSame('peak', $player->developmentStatus(now()));
    }

    public function test_game_creation_aborts_404_for_unknown_team(): void
    {
        $user = User::factory()->create();

        // Malformed id: previously a QueryException (500) from the
        // uuid-typed competition_teams lookup.
        try {
            app(GameCreationService::class)->create((string) $user->id, 'team-that-does-not-exist');
            $this->fail('expected NotFoundHttpException');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        // Well-formed UUID but no such team: previously "$team->parent_team_id
        // on null" further down.
        try {
            app(GameCreationService::class)->create((string) $user->id, (string) Str::uuid());
            $this->fail('expected NotFoundHttpException');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }
    }

    public function test_simulate_other_leagues_tolerates_null_country(): void
    {
        // Game::country is nullable in practice; previously this threw a
        // TypeError inside pickOtherCompetition(string $country).
        $game = Game::factory()->make(['country' => null]);

        app(SimulateOtherLeagues::class)->handle(new SeasonCompleted($game));

        $this->assertTrue(true);
    }

    public function test_advance_season_transition_progress_with_zero_total_steps(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $chunk = $this->mock(SeasonTransitionChunkService::class);
        $chunk->shouldReceive('runChunk')->once()->andReturn([
            'done' => false,
            'step' => 0,
            'totalSteps' => 0,
        ]);

        // Must not throw DivisionByZeroError.
        $response = app(AdvanceSeasonTransition::class)($game->id);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame(100, $data['progress']);
    }

    public function test_national_team_stats_tolerates_malformed_squad_json(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        DB::table('competitions')->updateOrInsert(
            ['id' => 'FRIENDLY'],
            ['name' => 'Friendly', 'country' => 'XX', 'tier' => 0, 'type' => 'cup', 'handler_type' => 'friendly', 'season' => '2026']
        );

        DB::table('tournament_summaries')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'FRIENDLY',
            'result_label' => 'Winners',
            'your_record' => json_encode([]),
            // Malformed: player entries without player_name / position keys.
            'summary_data' => json_encode([
                'your_squad_stats' => [
                    ['appearances' => 3, 'goals' => 1, 'assists' => 0],
                ],
            ]),
            'tournament_date' => '2026-07-01',
        ]);

        // Previously: "Undefined array key" warnings (ErrorException under test).
        $result = app(NationalTeamStatsService::class)->getPlayerFrequency($team->id);

        $this->assertCount(1, $result);
        $this->assertSame('?', $result->first()['player_name']);
        $this->assertSame('?', $result->first()['position']);
        $this->assertSame(3, $result->first()['total_appearances']);
    }
}
