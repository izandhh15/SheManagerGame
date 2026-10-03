<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use App\Modules\Social\Services\FriendshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Friend requests: send by username, accept/reject/remove, listing,
 * and the mutual-friends gate used by the careers pages.
 */
class FriendshipServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Local pgsql test DB (see phpunit.xml); independent of TestCase's override. */
    protected $connectionsToTransact = ['pgsql'];

    private FriendshipService $service;
    private User $alice;
    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FriendshipService::class);
        $this->alice = User::factory()->create(['username' => 'alice_fem']);
        $this->bob = User::factory()->create(['username' => 'bob_fem']);
    }

    public function test_send_request_creates_pending_friendship(): void
    {
        $result = $this->service->sendRequest($this->alice, 'bob_fem');

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('friendships', [
            'user_id' => $this->alice->id,
            'friend_id' => $this->bob->id,
            'status' => Friendship::STATUS_PENDING,
        ]);
    }

    public function test_send_request_validates(): void
    {
        // Unknown username.
        $this->assertFalse($this->service->sendRequest($this->alice, 'nobody_here')['ok']);

        // Self.
        $this->assertFalse($this->service->sendRequest($this->alice, 'alice_fem')['ok']);

        // Duplicate (either direction).
        $this->service->sendRequest($this->alice, 'bob_fem');
        $this->assertFalse($this->service->sendRequest($this->alice, 'bob_fem')['ok']);
        $this->assertFalse($this->service->sendRequest($this->bob, 'alice_fem')['ok']);
    }

    public function test_accept_requires_recipient(): void
    {
        $result = $this->service->sendRequest($this->alice, 'bob_fem');
        $id = $result['friendship']->id;

        // Sender cannot accept their own request.
        $this->assertFalse($this->service->accept($this->alice, $id)['ok']);

        // Recipient can.
        $this->assertTrue($this->service->accept($this->bob, $id)['ok']);
        $this->assertTrue($this->service->areFriends($this->alice->id, $this->bob->id));
        $this->assertTrue($this->service->areFriends($this->bob->id, $this->alice->id));
    }

    public function test_reject_removes_request(): void
    {
        $result = $this->service->sendRequest($this->alice, 'bob_fem');
        $id = $result['friendship']->id;

        $this->assertTrue($this->service->reject($this->bob, $id)['ok']);
        $this->assertDatabaseMissing('friendships', ['id' => $id]);
        $this->assertFalse($this->service->areFriends($this->alice->id, $this->bob->id));
    }

    public function test_remove_by_either_side(): void
    {
        $result = $this->service->sendRequest($this->alice, 'bob_fem');
        $id = $result['friendship']->id;
        $this->service->accept($this->bob, $id);

        // The sender removes.
        $this->assertTrue($this->service->remove($this->alice, $id)['ok']);
        $this->assertFalse($this->service->areFriends($this->alice->id, $this->bob->id));

        // Third party cannot remove.
        $carol = User::factory()->create(['username' => 'carol_fem']);
        $r2 = $this->service->sendRequest($this->alice, 'bob_fem');
        $this->assertFalse($this->service->remove($carol, $r2['friendship']->id)['ok']);
    }

    public function test_friends_lists_both_directions(): void
    {
        $carol = User::factory()->create(['username' => 'carol_fem']);

        $r1 = $this->service->sendRequest($this->alice, 'bob_fem');
        $this->service->accept($this->bob, $r1['friendship']->id);

        // carol -> alice, accepted by alice: alice sees carol too.
        $r2 = $this->service->sendRequest($carol, 'alice_fem');
        $this->service->accept($this->alice, $r2['friendship']->id);

        $names = $this->service->friends($this->alice)->pluck('username')->all();
        $this->assertEqualsCanonicalizing(['bob_fem', 'carol_fem'], $names);

        // Pending-only is not a friend.
        $dave = User::factory()->create(['username' => 'dave_fem']);
        $this->service->sendRequest($dave, 'alice_fem');
        $this->assertNotContains('dave_fem', $this->service->friends($this->alice)->pluck('username')->all());
    }

    public function test_pending_requests_only_for_recipient(): void
    {
        $this->service->sendRequest($this->alice, 'bob_fem');

        $this->assertCount(1, $this->service->pendingRequests($this->bob));
        $this->assertCount(0, $this->service->pendingRequests($this->alice));
        $this->assertCount(1, $this->service->sentRequests($this->alice));
    }
}
