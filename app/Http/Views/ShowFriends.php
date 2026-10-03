<?php

namespace App\Http\Views;

use App\Models\Friendship;
use App\Modules\Social\Services\FederationService;
use App\Modules\Social\Services\FriendshipService;
use Illuminate\Http\Request;

class ShowFriends
{
    public function __construct(
        private readonly FriendshipService $friendships,
        private readonly FederationService $federation,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Accepted friendships. Federated rows have no local "other" user:
        // the view gets a normalized array instead.
        $friendships = Friendship::with(['user', 'friend'])
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('friend_id', $user->id))
            ->get()
            ->map(fn ($f) => $this->presentRow($f, $user->id, true))
            ->sortBy(fn ($row) => $row['username'])
            ->values();

        $pending = $this->friendships->pendingRequests($user)
            ->map(fn ($f) => $this->presentRow($f, $user->id, false));

        $sent = $this->friendships->sentRequests($user)
            ->map(fn ($f) => $this->presentRow($f, $user->id, false));

        $federationEnabled = $this->federation->enabled();
        $peer = $federationEnabled
            ? $this->federation->peerDirectory($request->integer('peer_page', 1))
            : null;

        return view('friends.index', [
            'friendships' => $friendships,
            'pending' => $pending,
            'sent' => $sent,
            'federation' => [
                'enabled' => $federationEnabled,
                'peer_label' => $federationEnabled ? $this->federation->peerLabel() : null,
                'players' => $peer['data'] ?? [],
                'meta' => $peer['meta'] ?? [],
                'error' => $peer['error'] ?? false,
            ],
        ]);
    }

    /**
     * Normalized row for the view: local friendships resolve the other
     * User, federated ones expose the remote party + platform badge.
     */
    private function presentRow(Friendship $friendship, int|string $viewerId, bool $withCareers): array
    {
        if ($friendship->isFederated()) {
            return [
                'id' => $friendship->id,
                'username' => $friendship->remote_username,
                'club' => $friendship->remote_club,
                'is_federated' => true,
                'peer_label' => $friendship->peerLabel(),
                'careers_user_id' => null,
            ];
        }

        $other = (int) $friendship->user_id === (int) $viewerId
            ? $friendship->friend
            : $friendship->user;

        return [
            'id' => $friendship->id,
            'username' => $other?->username,
            'club' => null,
            'is_federated' => false,
            'peer_label' => null,
            'careers_user_id' => $withCareers ? $other?->id : null,
        ];
    }
}
