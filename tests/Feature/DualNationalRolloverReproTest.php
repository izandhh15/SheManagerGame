<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Listeners\DetectTournamentEnded;
use App\Modules\Season\Services\NationalTeamRolloverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Repro: Spain finishes WNL while in dual with Valencia.
 * Does the rollover to WQUEFA work? What does the user see?
 */
class DualNationalRolloverReproTest extends TestCase
{
    use RefreshDatabase;

    public function test_spain_wnl_end_rolls_over_to_wquefa(): void
    {
        $user = User::factory()->create();

        Competition::factory()->create(['id' => 'ESP1']);
        Competition::factory()->create(['id' => 'WNL']);
        Competition::factory()->create(['id' => 'WQUEFA']);

        $clubTeam = Team::factory()->create(['name' => 'Valencia CF', 'type' => 'club']);
        $clubGame = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $clubTeam->id,
            'game_mode' => Game::MODE_CAREER,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => '2026-11-25',
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
        ]);

        // Spain as a national team with raw name 'Spain' (accessor translates)
        $ntTeam = Team::factory()->create(['name' => 'Spain', 'type' => 'national', 'confederation' => 'UEFA']);
        $ntGame = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $ntTeam->id,
            'game_mode' => Game::MODE_TOURNAMENT,
            'competition_id' => 'WNL',
            'season' => '2026',
            'base_season' => '2026',
            'current_date' => '2026-11-25',
            'linked_game_id' => $clubGame->id,
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
        ]);

        // Spain's WNL: all played (competition over). One played match + standings.
        GameMatch::factory()->create([
            'game_id' => $ntGame->id,
            'home_team_id' => $ntTeam->id,
            'away_team_id' => Team::factory()->create(['type' => 'national'])->id,
            'scheduled_date' => '2026-11-25',
            'played' => true,
            'competition_id' => 'WNL',
        ]);
        \App\Models\GameStanding::create([
            'game_id' => $ntGame->id,
            'team_id' => $ntTeam->id,
            'competition_id' => 'WNL',
            'position' => 1,
            'played' => 6, 'won' => 5, 'drawn' => 1, 'lost' => 0,
            'points' => 16,
        ]);

        $rollover = app(NationalTeamRolloverService::class);
        fwrite(STDERR, "\n[repro] isContinuable: " . var_export($rollover->isContinuable($ntGame), true) . "\n");

        // Simulate what MatchFinalizationService does after the last match.
        app(DetectTournamentEnded::class)->detect($ntGame->refresh());

        $ntGame->refresh();
        fwrite(STDERR, "[repro] after detect: competition={$ntGame->competition_id} season={$ntGame->season} setup_completed_at=" . var_export($ntGame->setup_completed_at?->toDateTimeString(), true) . "\n");
        fwrite(STDERR, '[repro] deleting_at: ' . var_export($ntGame->deleting_at, true) . "\n");
        fwrite(STDERR, '[repro] WQUEFA matches: ' . GameMatch::where('game_id', $ntGame->id)->where('competition_id', 'WQUEFA')->count() . "\n");
        fwrite(STDERR, '[repro] total matches: ' . GameMatch::where('game_id', $ntGame->id)->count() . "\n");
        fwrite(STDERR, '[repro] archives: ' . \App\Models\SeasonArchive::where('game_id', $ntGame->id)->count() . "\n");

        // What does the user see on the Spain dashboard now?
        $show = $this->actingAs($user)->get(route('show-game', $ntGame->id));
        fwrite(STDERR, '[repro] spain show-game status: ' . $show->getStatusCode() . "\n");
        if ($show->isRedirect()) {
            fwrite(STDERR, '[repro] spain show-game redirect: ' . $show->headers->get('Location') . "\n");
        }

        // And the club dashboard (dual partner link)?
        $clubShow = $this->actingAs($user)->get(route('show-game', $clubGame->id));
        fwrite(STDERR, '[repro] club show-game status: ' . $clubShow->getStatusCode() . "\n");

        $this->assertTrue(true);
    }
}
