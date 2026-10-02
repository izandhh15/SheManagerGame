<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\NationalSquadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dual convocatoria bug (02-10-2026): the squad picker was prompted
 * on almost every visit because relevantWindow()'s 60-day lookahead
 * fired months early, and creation-time picks didn't always stamp a
 * window. A confirmed convocatoria must persist and never re-prompt.
 */
class DualSquadPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function nationalGame(User $user, string $date, ?string $confirmedWindow): Game
    {
        $team = Team::factory()->create(['name' => 'España', 'type' => 'national']);

        return Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => $date,
            'national_squad_window' => $confirmedWindow,
            'national_squad_player_ids' => ['a'],
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
        ]);
    }

    private function entersPicker(Game $game): bool
    {
        $response = $this->actingAs($game->user)->get(route('show-game', $game->id));

        return $response->isRedirect()
            && str_contains((string) $response->headers->get('Location'), 'national-squad');
    }

    public function test_confirmed_convocatoria_is_not_asked_again_before_the_window(): void
    {
        $user = User::factory()->create();
        // Sept window confirmed; visiting in August must not re-prompt.
        $game = $this->nationalGame($user, '2026-08-20', '2026-09-01');

        $this->assertFalse($this->entersPicker($game));
    }

    public function test_confirmed_convocatoria_is_not_asked_again_during_the_window(): void
    {
        $user = User::factory()->create();
        $game = $this->nationalGame($user, '2026-09-05', '2026-09-01');

        $this->assertFalse($this->entersPicker($game));
    }

    public function test_picker_fires_for_a_new_window_only_when_it_is_near(): void
    {
        $user = User::factory()->create();

        // Sept window confirmed, date just after it: the Oct window is
        // still >21 days away — no nagging two months early.
        $game = $this->nationalGame($user, '2026-09-12', '2026-09-01');
        $this->assertFalse($this->entersPicker($game));

        // Same situation but the Oct window is now near: prompt fires once.
        $game->update(['current_date' => '2026-09-20']);
        $this->assertTrue($this->entersPicker($game));
    }

    public function test_next_window_fallback_always_stamps_a_window_at_creation(): void
    {
        $user = User::factory()->create();
        // Early season: relevantWindow() is null (no break within 60 days
        // of July 1st), but nextWindow() still finds the September break.
        $game = $this->nationalGame($user, '2026-07-01', null);

        $this->assertNull(NationalSquadService::relevantWindow($game));
        $next = NationalSquadService::nextWindow($game);
        $this->assertNotNull($next);
        $this->assertSame('2026-09-01', $next['start']);
    }
}
