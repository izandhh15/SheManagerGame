<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * TRIAGE-B G16: LoginRequest checked isActivated() BEFORE Auth::attempt(),
 * returning auth.account_not_activated vs auth.failed — an oracle revealing
 * which emails are registered-but-inactive. All failures now share the
 * generic auth.failed message, while inactive accounts still can't log in.
 */
class LoginUnificationTest extends TestCase
{
    use RefreshDatabase;

    private function loginErrorMessage($response): ?string
    {
        return session('errors')->getBag('default')->first('email');
    }

    public function test_inactive_user_with_correct_password_gets_generic_error_and_stays_guest(): void
    {
        User::factory()->unverified()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(trans('auth.failed'), $this->loginErrorMessage($response));
        $this->assertGuest('web');
    }

    public function test_all_failure_modes_are_indistinguishable(): void
    {
        User::factory()->unverified()->create([
            'email' => 'inactive2@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $messages = [];

        $messages[] = $this->loginErrorMessage($this->post('/login', [
            'email' => 'inactive2@example.com', 'password' => 'correct-password',
        ]));

        $messages[] = $this->loginErrorMessage($this->post('/login', [
            'email' => 'inactive2@example.com', 'password' => 'wrong-password',
        ]));

        $messages[] = $this->loginErrorMessage($this->post('/login', [
            'email' => 'nobody-here@example.com', 'password' => 'whatever',
        ]));

        $expected = trans('auth.failed');
        foreach ($messages as $message) {
            $this->assertSame($expected, $message);
        }
        $this->assertStringNotContainsStringIgnoringCase(
            'activat',
            implode(' ', $messages),
            'no failure message may hint at activation state'
        );
    }

    public function test_active_user_with_correct_password_still_logs_in(): void
    {
        User::factory()->create([
            'email' => 'active@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'active@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'active@example.com')->first());
    }
}
