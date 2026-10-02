<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\SeasonInitializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NationalFixtureDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_national_competition_dates_not_shifted(): void
    {
        $user = User::factory()->create();
        Competition::factory()->create(['id' => 'WQUEFA', 'type' => 'league']);

        $team = Team::factory()->create(['name' => 'Spain', 'type' => 'national']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'WQUEFA',
            'season' => '2027', // post-rollover
            'base_season' => '2026',
            'setup_completed_at' => now(),
        ]);

        $teamIds = [$team->id];
        for ($i = 0; $i < 5; $i++) {
            $teamIds[] = Team::factory()->create(['type' => 'national'])->id;
        }
        foreach ($teamIds as $tid) {
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => 'WQUEFA',
                'team_id' => $tid,
            ]);
        }

        app(SeasonInitializationService::class)->generateLeagueFixtures($game->id, 'WQUEFA', '2027');

        $dates = GameMatch::where('game_id', $game->id)
            ->orderBy('scheduled_date')
            ->pluck('scheduled_date')
            ->map(fn ($d) => $d->format('Y'))
            ->unique()
            ->values()
            ->all();

        // schedule.json has 2027 dates; they must NOT be shifted to 2028.
        $this->assertContains('2027', $dates);
        $this->assertNotContains('2028', $dates);
    }
}
