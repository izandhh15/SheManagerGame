<?php

namespace Tests\Feature;

use App\Http\Views\Dashboard;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A corrupt save must never 500 the dashboard or the game entry route
 * (report from 02-10-2026: a user could not get past a 500 caused by
 * their own save data). Broken saves are quarantined with a visible
 * notice, healthy saves keep rendering, and entering a broken save
 * bounces back to the dashboard with a warning instead of erroring.
 */
class DashboardResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function userWithHealthySave(string $playerName = 'Mister Enforma'): User
    {
        $user = User::factory()->create();

        Game::factory()->create([
            'user_id' => $user->id,
            'player_name' => $playerName,
            'base_season' => '2026',
            'season' => '2026',
        ]);

        return $user;
    }

    private function healthySaveOf(User $user): Game
    {
        return Game::where('user_id', $user->id)->where('player_name', 'Mister Enforma')->firstOrFail();
    }

    /**
     * Point the save at a team row that no longer exists — the classic
     * corrupt save (reference-data reseed, half-finished deletion…).
     * Postgres would normally block this via the FK, which is exactly
     * why it has to be forced: in production the constraint was bypassed.
     */
    private function orphanTeamOf(Game $game): void
    {
        DB::statement("SET session_replication_role = 'replica'");

        try {
            DB::table('games')->where('id', $game->id)->update(['team_id' => (string) Str::uuid()]);
        } finally {
            DB::statement("SET session_replication_role = 'origin'");
        }
    }

    public function test_dashboard_returns_200_with_an_orphaned_team_save(): void
    {
        $user = $this->userWithHealthySave();

        $broken = Game::factory()->create([
            'user_id' => $user->id,
            'player_name' => 'Mister Roto',
            'base_season' => '2026',
            'season' => '2026',
        ]);
        $this->orphanTeamOf($broken);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // The quarantine notice is visible…
        $response->assertSee(__('game.broken_save_title'));
        $response->assertSee('Mister Roto');
        $response->assertSee(__('game.broken_save_delete'));
        // …and the healthy save still renders its card and continue link.
        $healthy = $this->healthySaveOf($user);
        $response->assertSee($healthy->team->name);
        $response->assertSee(route('show-game', $healthy->id));
        // The broken save is NOT rendered as a card: its URL appears exactly
        // once on the page — the delete form in the quarantine banner —
        // never as a "continue" link.
        $this->assertSame(1, substr_count($response->getContent(), route('show-game', $broken->id)));
    }

    public function test_dashboard_is_unchanged_when_every_save_is_healthy(): void
    {
        $user = $this->userWithHealthySave();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee(__('game.broken_save_title'));
        $response->assertDontSee(__('game.broken_save_delete'));
        $response->assertSee($this->healthySaveOf($user)->team->name);
    }

    public function test_entering_a_broken_save_redirects_to_dashboard_with_warning(): void
    {
        $user = $this->userWithHealthySave();

        $broken = Game::factory()->create([
            'user_id' => $user->id,
            'player_name' => 'Mister Roto',
            'base_season' => '2026',
            'season' => '2026',
        ]);
        $this->orphanTeamOf($broken);

        $response = $this->actingAs($user)->get(route('show-game', $broken->id));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('warning', __('game.save_load_failed'));
    }

    public function test_entering_an_unknown_game_id_is_rejected_by_the_owner_gate(): void
    {
        $user = $this->userWithHealthySave();

        // The game.owner middleware rejects unknown ids before the
        // controller runs; that behaviour is unchanged by the quarantine.
        $this->actingAs($user)
            ->get(route('show-game', (string) Str::uuid()))
            ->assertForbidden();
    }

    public function test_dashboard_probe_rejects_unreadable_json(): void
    {
        // White-box: Postgres' native json columns reject garbage at
        // write time, so corrupt JSON can only be simulated at the model
        // layer. The probe must still refuse to render such a save.
        $game = Game::factory()->make(['user_id' => User::factory()->create()->id]);
        $game->setRelation('team', Team::factory()->make());
        $attributes = $game->getAttributes();
        $attributes['pending_actions'] = '{this-is-not-json';
        $game->setRawAttributes($attributes, true);

        $probe = new \ReflectionMethod(Dashboard::class, 'assertGameRenders');

        $this->expectException(\Throwable::class);
        $probe->invoke(null, $game);
    }
}
