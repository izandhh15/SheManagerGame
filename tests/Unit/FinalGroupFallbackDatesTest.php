<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Season\Jobs\SetupTournamentGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: SetupTournamentGame::createFinalGroupFixtures() fell back to
 * hardcoded 2027 dates for EVERY final tournament when schedule.json had no
 * league dates — a 2028 Olympics / 2029 Euros would be dated in 2027. The
 * fallback is now per tournament year.
 */
class FinalGroupFallbackDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_dates_use_the_tournament_year(): void
    {
        $game = Game::factory()->create(['season' => '2027', 'current_date' => '2027-06-01']);
        $job = new SetupTournamentGame($game->id, $game->team_id);
        $method = new \ReflectionMethod($job, 'finalGroupFallbackDates');
        $method->setAccessible(true);

        $this->assertSame(
            ['2027-06-24', '2027-06-29', '2027-07-04'],
            $method->invoke($job, 'WWCU27'),
        );
        $this->assertSame(
            ['2028-06-24', '2028-06-29', '2028-07-04'],
            $method->invoke($job, 'WOLYMP'),
        );
        $this->assertSame(
            ['2029-06-24', '2029-06-29', '2029-07-04'],
            $method->invoke($job, 'WEURO'),
        );
    }

    public function test_unknown_competition_falls_back_to_2027(): void
    {
        $game = Game::factory()->create(['season' => '2027', 'current_date' => '2027-06-01']);
        $job = new SetupTournamentGame($game->id, $game->team_id);
        $method = new \ReflectionMethod($job, 'finalGroupFallbackDates');
        $method->setAccessible(true);

        $this->assertSame(
            ['2027-06-24', '2027-06-29', '2027-07-04'],
            $method->invoke($job, 'NOPE'),
        );
    }

    public function test_fixtures_use_fallback_dates_when_schedule_json_is_missing(): void
    {
        $teams = Team::factory()->count(4)->create();
        // 'WEUROX' has no data/2026/WEUROX/schedule.json → fallback dates.
        Competition::factory()->league()->create(['id' => 'WEUROX']);
        $game = Game::factory()->create([
            'team_id' => $teams[0]->id,
            'season' => '2029',
            'current_date' => '2029-06-01',
        ]);

        $job = new SetupTournamentGame($game->id, $game->team_id);
        $method = new \ReflectionMethod($job, 'createFinalGroupFixtures');
        $method->setAccessible(true);

        $method->invoke($job, 'WEUROX', ['A' => $teams->pluck('id')->all()]);

        $dates = GameMatch::where('game_id', $game->id)
            ->orderBy('scheduled_date')
            ->pluck('scheduled_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->unique()
            ->values()
            ->all();

        // Unknown id → 2027 skeleton (documented default); the point is the
        // fixtures are dated from the fallback, not left undated.
        $this->assertSame(['2027-06-24', '2027-06-29', '2027-07-04'], $dates);
    }
}
