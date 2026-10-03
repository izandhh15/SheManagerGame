<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\User;
use App\Modules\Social\Services\FriendshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug medio M24 (QA agent-21).
 *
 * Los ids de amistad no-UUID provocaban un 500 (QueryException 22P02) en
 * accept/reject/remove, porque `FriendshipService` llamaba a
 * `Friendship::find($id)` con el string crudo y la PK es `uuid` en Postgres.
 * Ahora el servicio valida el formato antes del `find()` y responde
 * `friends.not_found` tanto a nivel de servicio como por HTTP.
 */
class M24FriendshipUuidGuardTest extends TestCase
{
    use RefreshDatabase;

    private FriendshipService $service;
    private User $alice;
    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FriendshipService::class);
        $this->alice = User::factory()->create(['username' => 'm24alice']);
        $this->bob = User::factory()->create(['username' => 'm24bob']);
    }

    public function test_accept_garbage_id_returns_not_found_instead_of_500(): void
    {
        $result = $this->service->accept($this->bob, 'not-a-real-uuid');

        $this->assertFalse($result['ok']);
        $this->assertSame(__('friends.not_found'), $result['message']);
    }

    public function test_reject_garbage_id_returns_not_found_instead_of_500(): void
    {
        $result = $this->service->reject($this->bob, 'not-a-real-uuid');

        $this->assertFalse($result['ok']);
        $this->assertSame(__('friends.not_found'), $result['message']);
    }

    public function test_remove_garbage_id_returns_not_found_instead_of_500(): void
    {
        $result = $this->service->remove($this->alice, 'not-a-real-uuid');

        $this->assertFalse($result['ok']);
        $this->assertSame(__('friends.not_found'), $result['message']);
    }

    public function test_accept_http_with_garbage_id_does_not_500(): void
    {
        $this->actingAs($this->bob)
            ->post('/friends/not-a-real-uuid/accept')
            ->assertRedirect(route('friends.index'))
            ->assertSessionHas('error', __('friends.not_found'));
    }

    public function test_reject_http_with_garbage_id_does_not_500(): void
    {
        $this->actingAs($this->bob)
            ->post('/friends/not-a-real-uuid/reject')
            ->assertRedirect(route('friends.index'))
            ->assertSessionHas('error', __('friends.not_found'));
    }

    public function test_remove_http_with_garbage_id_does_not_500(): void
    {
        $this->actingAs($this->alice)
            ->delete('/friends/not-a-real-uuid')
            ->assertRedirect(route('friends.index'))
            ->assertSessionHas('error', __('friends.not_found'));
    }

    public function test_valid_uuid_still_accepts(): void
    {
        $result = $this->service->sendRequest($this->alice, 'm24bob');
        $this->assertTrue($result['ok']);

        $accept = $this->service->accept($this->bob, $result['friendship']->id);
        $this->assertTrue($accept['ok']);
    }
}
