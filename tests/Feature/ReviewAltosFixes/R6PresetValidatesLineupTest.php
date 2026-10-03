<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameTactics;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * R6 [ALTA]: SaveTacticalPreset::applyToMatch() (apply_now) wrote the 11
 * user-supplied UUIDs straight into the live match XI via saveLineup(),
 * skipping validateLineup() — a forged POST with rival stars' IDs fielded
 * them for your team. It now runs LineupService::validateLineup() first,
 * exactly like SaveLineup.
 */
class R6PresetValidatesLineupTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_now_with_rival_player_ids_is_rejected(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $opponent = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        GameTactics::create([
            'game_id' => $game->id,
            'default_formation' => '4-3-3',
            'default_mentality' => 'balanced',
            'default_playing_style' => 'balanced',
            'default_pressing' => 'standard',
            'default_defensive_line' => 'normal',
        ]);

        GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
            'scheduled_date' => Carbon::parse('2026-08-20'),
            'played' => false,
        ]);

        // 11 forged UUIDs: "rival stars" that belong to nobody in this game.
        $rivalIds = collect(range(1, 11))
            ->map(fn () => (string) Str::uuid())
            ->all();

        $response = $this->actingAs($user)->post(
            route('game.tactical-presets.save', $game->id),
            [
                'name' => 'Rival XI',
                'formation' => '4-3-3',
                'lineup' => $rivalIds,
                'mentality' => 'balanced',
                'playing_style' => 'balanced',
                'pressing' => 'standard',
                'defensive_line' => 'normal',
                'apply_now' => '1',
            ]
        );

        // Rejected by validateLineup() — back to the lineup screen with errors,
        // never applied to the live match.
        $response->assertRedirect(route('game.lineup', $game->id));
        $response->assertSessionHasErrors();
    }

    public function test_apply_now_with_valid_lineup_still_applies(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $opponent = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        GameTactics::create([
            'game_id' => $game->id,
            'default_formation' => '4-3-3',
            'default_mentality' => 'balanced',
            'default_playing_style' => 'balanced',
            'default_pressing' => 'standard',
            'default_defensive_line' => 'normal',
        ]);

        GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
            'scheduled_date' => Carbon::parse('2026-08-20'),
            'played' => false,
        ]);

        // Real squad: 1 GK + 4 DF + 3 MF + 3 FW satisfies 4-3-3 requirements.
        $positions = array_merge(
            ['Goalkeeper'],
            array_fill(0, 4, 'Centre-Back'),
            array_fill(0, 3, 'Central Midfield'),
            array_fill(0, 3, 'Centre-Forward'),
        );
        $playerIds = collect($positions)->map(fn (string $pos) => \App\Models\GamePlayer::factory()
            ->forGame($game)
            ->forTeam($team)
            ->create([
                'position' => $pos,
                'date_of_birth' => '2000-01-01',
            ])->id)->all();

        $response = $this->actingAs($user)->post(
            route('game.tactical-presets.save', $game->id),
            [
                'name' => 'My XI',
                'formation' => '4-3-3',
                'lineup' => $playerIds,
                'mentality' => 'balanced',
                'playing_style' => 'balanced',
                'pressing' => 'standard',
                'defensive_line' => 'normal',
                'apply_now' => '1',
            ]
        );

        // A legitimate squad XI passes validation and is applied.
        $response->assertRedirect(route('show-game', $game->id));
        $response->assertSessionHasNoErrors();
    }
}
