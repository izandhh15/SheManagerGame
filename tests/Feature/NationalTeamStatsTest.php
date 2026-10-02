<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\User;
use App\Modules\NationalTeam\Services\NationalTeamStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NationalTeamStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_national_stats_count_caps_and_goals(): void
    {
        $user = User::factory()->create();
        $spain = Team::factory()->create(['name' => 'Spain', 'type' => 'national']);
        $rival = Team::factory()->create(['name' => 'France', 'type' => 'national']);
        Competition::factory()->create(['id' => 'WNL']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $spain->id,
            'competition_id' => 'WNL',
            'season' => '2026',
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $spain->id,
            'player_id' => '11111111-1111-4111-8111-111111111111',
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $spain->id,
            'away_team_id' => $rival->id,
            'played' => true,
            'home_lineup' => [['game_player_id' => $player->id]],
            'away_lineup' => [],
        ]);

        MatchEvent::create([
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'game_player_id' => $player->id,
            'team_id' => $spain->id,
            'event_type' => 'goal',
            'minute' => 23,
        ]);

        $stats = app(NationalTeamStatsService::class)->nationalStats($game, $spain->id);

        $this->assertSame(1, $stats[$player->id]['caps']);
        $this->assertSame(1, $stats[$player->id]['goals']);
    }

    public function test_club_stats_from_dual_partner(): void
    {
        $user = User::factory()->create();
        $spain = Team::factory()->create(['name' => 'Spain', 'type' => 'national']);
        $valencia = Team::factory()->create(['name' => 'Valencia CF', 'type' => 'club']);
        Competition::factory()->create(['id' => 'WNL']);
        Competition::factory()->create(['id' => 'ESP1']);

        $clubGame = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $valencia->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
        ]);
        $ntGame = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $spain->id,
            'competition_id' => 'WNL',
            'season' => '2026',
            'linked_game_id' => $clubGame->id,
        ]);

        $clubPlayer = GamePlayer::factory()->create([
            'game_id' => $clubGame->id,
            'team_id' => $valencia->id,
            'player_id' => '22222222-2222-4222-8222-222222222222',
        ]);
        $clubPlayer->matchState()->update([
            'season_appearances' => 25,
            'goals' => 12,
            'assists' => 8,
        ]);

        $stats = app(NationalTeamStatsService::class)->clubStats($ntGame, ['22222222-2222-4222-8222-222222222222', '33333333-3333-4333-8333-333333333333']);

        $this->assertSame(25, $stats['22222222-2222-4222-8222-222222222222']['apps']);
        $this->assertSame(12, $stats['22222222-2222-4222-8222-222222222222']['goals']);
        $this->assertSame(8, $stats['22222222-2222-4222-8222-222222222222']['assists']);
        $this->assertArrayNotHasKey('unknown', $stats);
    }
}
