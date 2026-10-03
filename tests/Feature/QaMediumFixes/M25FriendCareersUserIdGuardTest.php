<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\User;
use App\Modules\Social\Services\FriendshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug medio M25 (QA agent-20 y agent-21).
 *
 * `GET /friends/{userId}/careers` con un userId no numérico devolvía 500
 * (QueryException 22P02: `invalid input syntax for type bigint`), porque
 * `ShowFriendCareers` llamaba a `areFriends()` — que compara contra la
 * columna bigint `user_id` — antes de validar el formato. Ahora los valores
 * no numéricos responden 404 sin tocar la BD.
 */
class M25FriendCareersUserIdGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_numeric_user_id_does_not_500(): void
    {
        $user = User::factory()->create(['username' => 'm25viewer']);

        $this->actingAs($user)
            ->get('/friends/abc/careers')
            ->assertNotFound();
    }

    public function test_uuid_like_user_id_does_not_500(): void
    {
        $user = User::factory()->create(['username' => 'm25viewer2']);

        $this->actingAs($user)
            ->get('/friends/550e8400-e29b-41d4-a716-446655440000/careers')
            ->assertNotFound();
    }

    public function test_numeric_stranger_still_gets_403(): void
    {
        $viewer = User::factory()->create(['username' => 'm25viewer3']);
        $stranger = User::factory()->create(['username' => 'm25stranger']);

        $this->actingAs($viewer)
            ->get("/friends/{$stranger->id}/careers")
            ->assertForbidden();
    }

    public function test_numeric_friend_still_gets_200(): void
    {
        $alice = User::factory()->create(['username' => 'm25alice']);
        $bob = User::factory()->create(['username' => 'm25bob']);

        $service = app(FriendshipService::class);
        $sent = $service->sendRequest($alice, 'm25bob');
        $this->assertTrue($sent['ok']);
        $this->assertTrue($service->accept($bob, $sent['friendship']->id)['ok']);

        $this->actingAs($alice)
            ->get("/friends/{$bob->id}/careers")
            ->assertOk();
    }
}
