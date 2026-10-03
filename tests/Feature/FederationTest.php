<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use App\Modules\Social\Services\FederationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Federation between platform instances: HMAC signature handshake, public
 * player directory, and the cross-instance friend-request flow (with the
 * peer HTTP calls mocked).
 */
class FederationTest extends TestCase
{
    use RefreshDatabase;

    /** Local pgsql test DB (see phpunit.xml); independent of TestCase's override. */
    protected $connectionsToTransact = ['pgsql'];

    private FederationService $federation;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'federation.peer_url' => 'https://peer.test',
            'federation.secret' => 'test-secret',
            'federation.enabled' => true,
        ]);

        $this->federation = app(FederationService::class);
    }

    // ------------------------------------------------------------------
    // Signature handshake
    // ------------------------------------------------------------------

    private function signedRequest(string $body, ?string $timestamp = null, ?string $signature = null): Request
    {
        $timestamp ??= (string) time();
        $signature ??= hash_hmac('sha256', $timestamp."\n".$body, 'test-secret');

        return Request::create(
            '/api/federation/friend-request',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_FEDERATION_TIMESTAMP' => $timestamp,
                'HTTP_X_FEDERATION_SIGNATURE' => $signature,
            ],
            $body
        );
    }

    public function test_valid_signature_verifies(): void
    {
        $body = json_encode(['hello' => 'world']);

        $this->assertTrue($this->federation->verifyRequest($this->signedRequest($body)));
    }

    public function test_tampered_body_fails_verification(): void
    {
        $body = json_encode(['hello' => 'world']);
        $request = $this->signedRequest($body);

        // Tamper after signing: rebuild with a different body, same headers.
        $tampered = Request::create(
            '/api/federation/friend-request',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_FEDERATION_TIMESTAMP' => $request->header('X-Federation-Timestamp'),
                'HTTP_X_FEDERATION_SIGNATURE' => $request->header('X-Federation-Signature'),
            ],
            json_encode(['hello' => 'mallory'])
        );

        $this->assertFalse($this->federation->verifyRequest($tampered));
    }

    public function test_old_timestamp_is_rejected(): void
    {
        $body = json_encode(['hello' => 'world']);
        $old = (string) (time() - 600);

        $this->assertFalse($this->federation->verifyRequest($this->signedRequest($body, $old)));
    }

    public function test_missing_headers_fail_verification(): void
    {
        $request = Request::create('/api/federation/friend-request', 'POST', [], [], [], [], '{}');

        $this->assertFalse($this->federation->verifyRequest($request));
    }

    private function postSigned(string $uri, array $payload, string $secret = 'test-secret'): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp."\n".$body, $secret);

        return $this->call(
            'POST',
            $uri,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_FEDERATION_TIMESTAMP' => $timestamp,
                'HTTP_X_FEDERATION_SIGNATURE' => $signature,
            ],
            $body
        );
    }

    // ------------------------------------------------------------------
    // Public directory
    // ------------------------------------------------------------------

    public function test_directory_lists_players_without_sensitive_data(): void
    {
        User::factory()->create(['username' => 'alice_fem', 'email' => 'alice@example.com']);
        User::factory()->create(['username' => 'bob_fem', 'email' => 'bob@example.com']);
        User::factory()->create(['username' => null]);

        $response = $this->getJson('/api/federation/players');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('alice_fem', $data[0]['username']);
        $this->assertArrayNotHasKey('email', $data[0]);
        $this->assertArrayHasKey('club', $data[0]);
        $this->assertArrayHasKey('season', $data[0]);
        $this->assertArrayHasKey('meta', $response->json());
    }

    public function test_directory_404_when_disabled(): void
    {
        config(['federation.enabled' => false]);

        $this->getJson('/api/federation/players')->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Incoming friend request
    // ------------------------------------------------------------------

    public function test_incoming_request_creates_pending_friendship(): void
    {
        $bob = User::factory()->create(['username' => 'bob_fem']);

        $response = $this->postSigned('/api/federation/friend-request', [
            'from_username' => 'alice_remote',
            'from_user_id' => 999,
            'from_club' => 'Valencia CF Femenino',
            'from_request_uuid' => '11111111-2222-3333-4444-555555555555',
            'from_instance' => 'https://peer.test',
            'to_username' => 'bob_fem',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('friendships', [
            'user_id' => $bob->id,
            'friend_id' => null,
            'status' => Friendship::STATUS_PENDING,
            'is_federated' => true,
            'is_remote_sender' => true,
            'remote_username' => 'alice_remote',
            'remote_instance' => 'https://peer.test',
            'remote_request_uuid' => '11111111-2222-3333-4444-555555555555',
        ]);
    }

    public function test_incoming_request_rejects_bad_signature(): void
    {
        User::factory()->create(['username' => 'bob_fem']);

        $response = $this->postSigned('/api/federation/friend-request', [
            'from_username' => 'alice_remote',
            'from_user_id' => 999,
            'from_request_uuid' => '11111111-2222-3333-4444-555555555555',
            'from_instance' => 'https://peer.test',
            'to_username' => 'bob_fem',
        ], 'wrong-secret');

        $response->assertForbidden();
        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_incoming_request_unknown_recipient_404(): void
    {
        $response = $this->postSigned('/api/federation/friend-request', [
            'from_username' => 'alice_remote',
            'from_user_id' => 999,
            'from_request_uuid' => '11111111-2222-3333-4444-555555555555',
            'from_instance' => 'https://peer.test',
            'to_username' => 'nobody_here',
        ]);

        $response->assertNotFound()->assertJson(['ok' => false, 'error' => 'user_not_found']);
    }

    public function test_incoming_request_is_idempotent(): void
    {
        User::factory()->create(['username' => 'bob_fem']);

        $payload = [
            'from_username' => 'alice_remote',
            'from_user_id' => 999,
            'from_request_uuid' => '11111111-2222-3333-4444-555555555555',
            'from_instance' => 'https://peer.test',
            'to_username' => 'bob_fem',
        ];

        $first = $this->postSigned('/api/federation/friend-request', $payload);
        $second = $this->postSigned('/api/federation/friend-request', $payload);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('request_uuid'), $second->json('request_uuid'));
        $this->assertDatabaseCount('friendships', 1);
    }

    // ------------------------------------------------------------------
    // Outgoing request + accept/reject propagation (peer mocked)
    // ------------------------------------------------------------------

    public function test_outgoing_request_notifies_peer_with_valid_signature(): void
    {
        $alice = User::factory()->create(['username' => 'alice_fem']);

        Http::fake([
            'peer.test/*' => Http::response(['ok' => true, 'request_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'], 200),
        ]);

        $result = $this->federation->sendRequest($alice, 'bob_remote');

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($req) {
            if ($req->url() !== 'https://peer.test/api/federation/friend-request') {
                return false;
            }
            $ts = $req->header('X-Federation-Timestamp')[0] ?? '';
            $sig = $req->header('X-Federation-Signature')[0] ?? '';
            $expected = hash_hmac('sha256', $ts."\n".$req->body(), 'test-secret');

            return $ts !== '' && hash_equals($expected, $sig);
        });

        $this->assertDatabaseHas('friendships', [
            'user_id' => $alice->id,
            'friend_id' => null,
            'status' => Friendship::STATUS_PENDING,
            'is_federated' => true,
            'remote_username' => 'bob_remote',
            'remote_request_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);
    }

    public function test_outgoing_request_rolls_back_when_peer_unreachable(): void
    {
        $alice = User::factory()->create(['username' => 'alice_fem']);

        Http::fake(['peer.test/*' => Http::response(null, 500)]);

        $result = $this->federation->sendRequest($alice, 'bob_remote');

        $this->assertFalse($result['ok']);
        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_accept_propagates_to_peer(): void
    {
        $bob = User::factory()->create(['username' => 'bob_fem']);

        // Simulate a request received from the peer.
        $incoming = $this->federation->handleIncomingRequest([
            'from_username' => 'alice_remote',
            'from_user_id' => 999,
            'from_club' => 'Valencia CF Femenino',
            'from_request_uuid' => '11111111-2222-3333-4444-666666666666',
            'from_instance' => 'https://peer.test',
            'to_username' => 'bob_fem',
        ]);
        $this->assertTrue($incoming['ok']);

        Http::fake(['peer.test/*' => Http::response(['ok' => true], 200)]);

        $service = app(\App\Modules\Social\Services\FriendshipService::class);
        $result = $service->accept($bob, $incoming['request_uuid']);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($req) {
            return $req->url() === 'https://peer.test/api/federation/friend-accept'
                && ($req['request_uuid'] ?? null) === '11111111-2222-3333-4444-666666666666';
        });

        $this->assertDatabaseHas('friendships', [
            'id' => $incoming['request_uuid'],
            'status' => Friendship::STATUS_ACCEPTED,
        ]);
    }

    public function test_peer_accept_notification_marks_local_row_accepted(): void
    {
        $alice = User::factory()->create(['username' => 'alice_fem']);

        $row = Friendship::create([
            'user_id' => $alice->id,
            'friend_id' => null,
            'status' => Friendship::STATUS_PENDING,
            'is_federated' => true,
            'is_remote_sender' => false,
            'remote_username' => 'bob_remote',
            'remote_instance' => 'https://peer.test',
            'remote_request_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        $response = $this->postSigned('/api/federation/friend-accept', [
            'request_uuid' => $row->id,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(
            Friendship::STATUS_ACCEPTED,
            $row->fresh()->status
        );
    }

    public function test_peer_reject_notification_removes_local_row(): void
    {
        $alice = User::factory()->create(['username' => 'alice_fem']);

        $row = Friendship::create([
            'user_id' => $alice->id,
            'friend_id' => null,
            'status' => Friendship::STATUS_PENDING,
            'is_federated' => true,
            'is_remote_sender' => false,
            'remote_username' => 'bob_remote',
            'remote_instance' => 'https://peer.test',
            'remote_request_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        $response = $this->postSigned('/api/federation/friend-reject', [
            'request_uuid' => $row->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('friendships', ['id' => $row->id]);
    }

    public function test_signed_endpoints_reject_unsigned_calls(): void
    {
        $this->postJson('/api/federation/friend-accept', ['request_uuid' => 'x'])
            ->assertForbidden();
        $this->postJson('/api/federation/friend-reject', ['request_uuid' => 'x'])
            ->assertForbidden();
        $this->postJson('/api/federation/friend-remove', ['request_uuid' => 'x'])
            ->assertForbidden();
    }
}
