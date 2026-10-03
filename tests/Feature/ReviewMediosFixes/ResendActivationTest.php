<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\User;
use App\Notifications\ActivateAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * TRIAGE-B G17: activation-sent.blade.php's "Reenviar" pointed at
 * password.request (a dead end). New POST /resend-activation route
 * (activation.resend) re-issues the activation email with throttle, and —
 * like the login fix — never reveals whether the address is registered or
 * active.
 */
class ResendActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_exists_as_post_with_throttle(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('activation.resend');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods);
        $this->assertNotContains('GET', $route->methods);
        $this->assertContains('throttle:3,1', array_map(
            fn ($m) => is_string($m) ? $m : get_class($m),
            $route->gatherMiddleware()
        ));
    }

    public function test_resends_activation_email_to_inactive_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['email' => 'inactive@example.com']);

        $response = $this->post(route('activation.resend'), [
            'email' => 'inactive@example.com',
        ]);

        $response->assertSessionHas('status', __('auth.activation_sent_body'));
        Notification::assertSentTo($user, ActivateAccount::class);
    }

    public function test_unknown_email_gets_same_response_and_no_email(): void
    {
        Notification::fake();

        $response = $this->post(route('activation.resend'), [
            'email' => 'ghost-nobody@example.com',
        ]);

        $response->assertSessionHas('status', __('auth.activation_sent_body'));
        Notification::assertNothingSent();
    }

    public function test_active_user_gets_same_response_and_no_email(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'active@example.com']);

        $response = $this->post(route('activation.resend'), [
            'email' => 'active@example.com',
        ]);

        $response->assertSessionHas('status', __('auth.activation_sent_body'));
        Notification::assertNothingSent();
    }

    public function test_logged_in_inactive_user_resends_without_email_field(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['email' => 'me@example.com']);

        $response = $this->actingAs($user)->post(route('activation.resend'));

        $response->assertSessionHas('status', __('auth.activation_sent_body'));
        Notification::assertSentTo($user, ActivateAccount::class);
    }

    public function test_throttle_kicks_in_after_three_attempts(): void
    {
        Notification::fake();

        User::factory()->unverified()->create(['email' => 'throttled@example.com']);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('activation.resend'), ['email' => 'throttled@example.com'])
                ->assertSessionHas('status');
        }

        $this->post(route('activation.resend'), ['email' => 'throttled@example.com'])
            ->assertStatus(429);
    }
}
