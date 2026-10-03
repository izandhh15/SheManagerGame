<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * TRIAGE-B G16: ProfileController::destroy() marked deleting_at + logged out
 * BEFORE DeleteUserJob::dispatch() — a dispatch failure left games in a
 * "deleting" limbo. Marking and dispatch now happen in one transaction with
 * dispatch after-commit.
 *
 * NOTE: no RefreshDatabase here on purpose — the after-commit dispatch only
 * fires when the controller's transaction really commits, which never
 * happens inside the test's rolled-back transaction. Cleanup is manual.
 */
class ProfileDestroyAtomicityTest extends TestCase
{
    public function test_destroy_deletes_account_and_games_without_limbo(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-pw')]);
        Game::factory()->create(['user_id' => $user->id]);
        Game::factory()->create(['user_id' => $user->id]);

        try {
            $response = $this->actingAs($user)->delete('/profile', [
                'password' => 'secret-pw',
            ]);

            $response->assertRedirect('/');
            $this->assertGuest();

            $this->assertNull(
                User::find($user->id),
                'user row must be deleted by the job — not left alive'
            );
            $this->assertSame(
                0,
                Game::where('user_id', $user->id)->count(),
                'no game may remain stuck in deleting_at limbo'
            );
        } finally {
            Game::where('user_id', $user->id)->delete();
            User::where('id', $user->id)->delete();
        }
    }

    public function test_destroy_rejects_wrong_password_and_changes_nothing(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-pw')]);
        $game = Game::factory()->create(['user_id' => $user->id]);

        try {
            $response = $this->actingAs($user)->delete('/profile', [
                'password' => 'wrong-pw',
            ]);

            $response->assertSessionHasErrors('password', null, 'userDeletion');
            $this->assertNotNull(User::find($user->id));
            $this->assertNull(Game::find($game->id)->deleting_at);
            $this->assertAuthenticatedAs($user);
        } finally {
            Game::where('user_id', $user->id)->delete();
            User::where('id', $user->id)->delete();
        }
    }
}
