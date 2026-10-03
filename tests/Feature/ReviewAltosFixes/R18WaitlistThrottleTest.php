<?php

namespace Tests\Feature\ReviewAltosFixes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * R18 [ALTA] — POST /api/waitlist was public with no throttle while
 * JoinWaitlist validates `email:rfc,dns` (one DNS lookup per request) and
 * BetaInviteService::invite() queues an invitation email per new address —
 * usable as a spam relay / mail-quota burner.
 */
class R18WaitlistThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_waitlist_route_has_throttle_middleware(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(
            fn ($r) => $r->uri() === 'api/waitlist' && in_array('POST', $r->methods(), true)
        );

        $this->assertNotNull($route, 'POST /api/waitlist route must exist');

        $middleware = $route->gatherMiddleware();

        $this->assertContains('throttle:6,1', $middleware);
    }

    public function test_waitlist_rate_limits_after_six_requests(): void
    {
        // Validation fails on these addresses (no DNS), but the throttle
        // middleware counts attempts before the controller runs, so the
        // 7th request must be 429 regardless of the validation outcome.
        $payload = fn (int $i) => [
            'name' => "Tester {$i}",
            'email' => "waitlist-probe-{$i}@invalid.invalid",
        ];

        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/waitlist', $payload($i));
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        $this->postJson('/api/waitlist', $payload(6))->assertStatus(429);
    }
}
