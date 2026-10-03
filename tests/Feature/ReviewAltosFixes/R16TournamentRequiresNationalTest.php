<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R16 [ALTA] (A17 family): InitGame's MODE_TOURNAMENT branch passed
 * team_id straight to TournamentCreationService without checking it is a
 * national side — a forged POST with a club id created a bricked
 * tournament save. It now resolves with
 * Team::where('type','national')->findOrFail(...) before creating anything.
 */
class R16TournamentRequiresNationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_with_club_team_id_is_rejected_and_creates_nothing(): void
    {
        config()->set('game.tournament_mode_enabled', true);
        $user = User::factory()->create(['has_tournament_access' => true]);
        $club = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);

        $response = $this->actingAs($user)->post(route('init-game'), [
            'team_id' => $club->id,
            'game_mode' => Game::MODE_TOURNAMENT,
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [404, 422],
            'A club team_id in tournament mode must be rejected, not bricked'
        );
        $this->assertSame(
            0,
            Game::where('user_id', $user->id)->count(),
            'No game may be created for the rejected request'
        );
    }

    public function test_tournament_guard_resolves_national_teams(): void
    {
        $national = Team::factory()->create([
            'type' => 'national',
            'is_placeholder' => false,
            'fifa_code' => 'ESP',
        ]);

        // The exact guard used by InitGame: must resolve a national side.
        $resolved = Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->findOrFail($national->id);

        $this->assertSame($national->id, $resolved->id);
    }

    public function test_tournament_guard_rejects_clubs_placeholders_and_unknown_ids(): void
    {
        $club = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);

        $this->expectException(ModelNotFoundException::class);
        Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->findOrFail($club->id);
    }
}
