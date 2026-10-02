<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\NationalTeamRolloverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NationalTeamEvolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollover_evolves_squad(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $spain = Team::factory()->create(['name' => 'Spain', 'type' => 'national']);
        \App\Models\Competition::factory()->create(['id' => 'WNL']);
        \App\Models\Competition::factory()->create(['id' => 'WQUEFA']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $spain->id,
            'competition_id' => 'WNL',
            'season' => '2026',
            'setup_completed_at' => now(),
            'current_date' => '2026-07-01',
        ]);

        // A veteran who should be a retirement candidate and a youngster
        // who should develop.
        $veteran = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $spain->id,
            'is_squad_member' => true,
            'date_of_birth' => '1990-01-01', // 36 years old
            'overall_score' => 85,
            'retiring_at_season' => '2026', // announced last season
        ]);
        $youngster = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $spain->id,
            'is_squad_member' => true,
            'date_of_birth' => '2006-01-01', // 20 years old
            'overall_score' => 75,
        ]);

        // No unplayed matches so the tournament is "ended".
        GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $spain->id,
            'played' => true,
        ]);

        $squadBefore = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $spain->id)
            ->where('is_squad_member', true)
            ->count();

        app(NationalTeamRolloverService::class)->rollover($game);

        // The veteran who announced retirement is gone.
        $this->assertNull(GamePlayer::find($veteran->id));

        // Youth intake added new prospects.
        $squadAfter = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $spain->id)
            ->where('is_squad_member', true)
            ->count();
        $this->assertGreaterThan($squadBefore - 1, $squadAfter);

        // New prospects are young.
        $youngProspects = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $spain->id)
            ->where('is_squad_member', true)
            ->where('id', '!=', $youngster->id)
            ->get();
        foreach ($youngProspects as $prospect) {
            $age = now()->diffInYears($prospect->date_of_birth);
            $this->assertLessThanOrEqual(23, $age);
        }
    }
}
