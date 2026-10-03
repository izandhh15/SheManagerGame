<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use App\Modules\Social\Services\FriendshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA bugs A14 + A15 (friends, 03-10-2026).
 *
 * A14: friends/index.blade.php printed the literal text
 *      {{ $row['username'] }} etc. because it used @{{ }} (Blade-escaped echo).
 *      Fixed to {{ '@'.$var['username'] }} so usernames render as @user.
 *
 * A15: federated INCOMING requests (friend_id NULL, is_remote_sender = true)
 *      fell into sentRequests() and never into pendingRequests(), so the
 *      recipient could not accept them from the UI. Both queries now take the
 *      request direction into account.
 */
class QaA14A15FriendsTest extends TestCase
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

    private function makeFederatedRow(User $local, bool $remoteSent): Friendship
    {
        return Friendship::create([
            'user_id' => $local->id,
            'friend_id' => null,
            'status' => Friendship::STATUS_PENDING,
            'is_federated' => true,
            'is_remote_sender' => $remoteSent,
            'remote_username' => 'remote_player',
            'remote_instance' => 'https://shemanager.vercel.app',
        ]);
    }

    // ---------- A15: direction-aware pending/sent ----------

    public function test_incoming_federated_request_appears_in_pending_not_sent(): void
    {
        // Remote side sent it; the local user received it.
        $this->makeFederatedRow($this->alice, true);

        $pending = $this->service->pendingRequests($this->alice);
        $sent = $this->service->sentRequests($this->alice);

        $this->assertCount(1, $pending, 'incoming federated request must be pending');
        $this->assertSame('remote_player', $pending->first()->remote_username);
        $this->assertCount(0, $sent, 'incoming federated request must NOT be in sent');
    }

    public function test_outgoing_federated_request_appears_in_sent_not_pending(): void
    {
        // Local user sent it to the remote side.
        $this->makeFederatedRow($this->alice, false);

        $this->assertCount(0, $this->service->pendingRequests($this->alice), 'outgoing federated request must NOT be in pending');
        $this->assertCount(1, $this->service->sentRequests($this->alice), 'outgoing federated request must be sent');
    }

    public function test_local_request_directions_still_correct(): void
    {
        $this->service->sendRequest($this->alice, 'bob_fem');

        // Recipient side.
        $this->assertCount(1, $this->service->pendingRequests($this->bob));
        $this->assertCount(0, $this->service->sentRequests($this->bob));

        // Sender side.
        $this->assertCount(0, $this->service->pendingRequests($this->alice));
        $this->assertCount(1, $this->service->sentRequests($this->alice));
    }

    public function test_incoming_federated_request_can_be_accepted_at_service_level(): void
    {
        $friendship = $this->makeFederatedRow($this->alice, true);

        $result = $this->service->accept($this->alice, $friendship->id);

        $this->assertTrue($result['ok']);
        $this->assertTrue($friendship->fresh()->isAccepted());
        $this->assertCount(0, $this->service->pendingRequests($this->alice));
    }

    // ---------- A14: view renders @username, no literal {{ }} ----------

    private function viewRow(string $id, string $username, bool $federated = false): array
    {
        return [
            'id' => $id,
            'username' => $username,
            'club' => $federated ? 'Peer FC' : null,
            'is_federated' => $federated,
            'peer_label' => $federated ? 'Vercel' : null,
            'careers_user_id' => null,
        ];
    }

    private function baseViewData(array $overrides = []): array
    {
        return array_merge([
            'friendships' => collect(),
            'pending' => collect(),
            'sent' => collect(),
            'federation' => [
                'enabled' => false,
                'peer_label' => null,
                'players' => [],
                'meta' => [],
                'error' => false,
            ],
        ], $overrides);
    }

    public function test_friends_view_renders_at_usernames_not_literal_blade(): void
    {
        $html = view('friends.index', $this->baseViewData([
            'friendships' => collect([$this->viewRow('f1', 'alice_fem')]),
            'pending' => collect([$this->viewRow('p1', 'bob_fem', true)]),
            'sent' => collect([$this->viewRow('s1', 'carol_fem')]),
        ]))->render();

        // Usernames show up as @user in all sections.
        $this->assertStringContainsString('@alice_fem', $html);
        $this->assertStringContainsString('@bob_fem', $html);
        $this->assertStringContainsString('@carol_fem', $html);

        // No literal Blade echo text anywhere.
        $this->assertStringNotContainsString("{{ \$row['username'] }}", $html);
        $this->assertStringNotContainsString("{{ \$req['username'] }}", $html);
        $this->assertStringNotContainsString("{{ \$player['username'] }}", $html);
    }

    public function test_federation_directory_renders_at_usernames(): void
    {
        $html = view('friends.index', $this->baseViewData([
            'federation' => [
                'enabled' => true,
                'peer_label' => 'Vercel',
                'players' => [
                    ['username' => 'remote_girl', 'club' => 'Remote FC', 'season' => '2026/27'],
                ],
                'meta' => [],
                'error' => false,
            ],
        ]))->render();

        $this->assertStringContainsString('@remote_girl', $html);
        $this->assertStringNotContainsString("{{ \$player['username'] }}", $html);
    }

    public function test_pending_federated_row_shows_accept_button(): void
    {
        $friendship = $this->makeFederatedRow($this->alice, true);
        $row = $this->viewRow($friendship->id, 'remote_player', true);

        $view = $this->view('friends.index', $this->baseViewData([
            'pending' => collect([$row]),
        ]));

        $view->assertSee('@remote_player', false);
        // The pending section exposes the accept/reject forms for the recipient.
        $view->assertSee(route('friends.accept', $friendship->id), false);
        $view->assertSee(route('friends.reject', $friendship->id), false);
    }
}
