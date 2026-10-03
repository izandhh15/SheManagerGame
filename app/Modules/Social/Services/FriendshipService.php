<?php

namespace App\Modules\Social\Services;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Friend requests between users. Friendships are mutual once accepted;
 * either side can remove them. Only accepted friendships grant access to
 * a friend's careers.
 */
class FriendshipService
{
    public function __construct(
        private readonly FederationService $federation,
    ) {}

    /**
     * Whether the given user is the recipient of the request (the one who
     * may accept or reject it). For federated rows the local user is always
     * user_id; is_remote_sender tells whether they received it.
     */
    private function isRecipient(Friendship $friendship, User $user): bool
    {
        if ($friendship->isFederated()) {
            return (int) $friendship->user_id === (int) $user->id
                && (bool) $friendship->is_remote_sender;
        }

        return (int) $friendship->friend_id === (int) $user->id;
    }

    /**
     * Send a friend request to another user by username.
     *
     * @return array{ok: bool, message: string, friendship?: Friendship}
     */
    public function sendRequest(User $user, string $username): array
    {
        $username = trim($username);

        if ($username === '') {
            return ['ok' => false, 'message' => __('friends.username_required')];
        }

        $target = User::where('username', $username)->first();

        if (! $target) {
            return ['ok' => false, 'message' => __('friends.user_not_found')];
        }

        if ((int) $target->id === (int) $user->id) {
            return ['ok' => false, 'message' => __('friends.cannot_add_self')];
        }

        if ($this->findBetween($user->id, $target->id)) {
            return ['ok' => false, 'message' => __('friends.already_exists')];
        }

        $friendship = Friendship::create([
            'user_id' => $user->id,
            'friend_id' => $target->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        return ['ok' => true, 'message' => __('friends.request_sent'), 'friendship' => $friendship];
    }

    /**
     * Look up a friendship by id, guarding against malformed ids.
     *
     * The PK is a uuid in Postgres: any non-UUID string would make the
     * driver throw a QueryException (22P02) before we could answer
     * `friends.not_found`. Anything that isn't a UUID simply doesn't exist.
     */
    private function findById(string $friendshipId): ?Friendship
    {
        if (! Str::isUuid($friendshipId)) {
            return null;
        }

        return Friendship::find($friendshipId);
    }

    /**
     * Accept a pending request. Only the recipient can accept.
     */
    public function accept(User $user, string $friendshipId): array
    {
        $friendship = $this->findById($friendshipId);

        if (! $friendship || ! $this->isRecipient($friendship, $user)) {
            return ['ok' => false, 'message' => __('friends.not_found')];
        }

        if ($friendship->isAccepted()) {
            return ['ok' => false, 'message' => __('friends.already_exists')];
        }

        $friendship->update(['status' => Friendship::STATUS_ACCEPTED]);

        // Clean up any reverse-direction duplicate (double-send race): the
        // unique (user_id, friend_id) index can't prevent a row in the
        // opposite direction, and it must not linger next to the accepted one.
        Friendship::where('user_id', $friendship->friend_id)
            ->where('friend_id', $friendship->user_id)
            ->where('id', '!=', $friendship->id)
            ->delete();

        if ($friendship->isFederated()) {
            $this->federation->propagateAccept($friendship);
        }

        return ['ok' => true, 'message' => __('friends.request_accepted')];
    }

    /**
     * Reject a pending request. Only the recipient can reject; the row is removed.
     */
    public function reject(User $user, string $friendshipId): array
    {
        $friendship = $this->findById($friendshipId);

        if (! $friendship || ! $this->isRecipient($friendship, $user)) {
            return ['ok' => false, 'message' => __('friends.not_found')];
        }

        $wasFederated = $friendship->isFederated();
        $friendship->delete();

        if ($wasFederated) {
            $this->federation->propagateReject($friendship);
        }

        return ['ok' => true, 'message' => __('friends.request_rejected')];
    }

    /**
     * Remove a friendship (or cancel a sent pending request). Either side can do it.
     */
    public function remove(User $user, string $friendshipId): array
    {
        $friendship = $this->findById($friendshipId);

        if (! $friendship
            || ((int) $friendship->user_id !== (int) $user->id
                && (int) $friendship->friend_id !== (int) $user->id)
        ) {
            return ['ok' => false, 'message' => __('friends.not_found')];
        }

        $wasFederated = $friendship->isFederated();
        $wasAccepted = $friendship->isAccepted();
        $friendship->delete();

        // Remove every remaining row between the pair in BOTH directions:
        // reverse-direction duplicates (double-send race) that the unique
        // (user_id, friend_id) index can't prevent must not survive a remove.
        Friendship::where(function ($q) use ($friendship) {
            $q->where('user_id', $friendship->user_id)
                ->where('friend_id', $friendship->friend_id);
        })->orWhere(function ($q) use ($friendship) {
            $q->where('user_id', $friendship->friend_id)
                ->where('friend_id', $friendship->user_id);
        })->delete();

        if ($wasFederated) {
            if ($wasAccepted) {
                $this->federation->propagateRemove($friendship);
            } else {
                // Cancelling a pending federated request: tell the peer to
                // drop its mirror row too.
                $this->federation->propagateReject($friendship);
            }
        }

        return ['ok' => true, 'message' => __('friends.removed')];
    }

    /**
     * Accepted friends of a user (both directions).
     *
     * @return Collection<int, User>
     */
    public function friends(User $user): Collection
    {
        $ids = Friendship::where('status', Friendship::STATUS_ACCEPTED)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('friend_id', $user->id))
            ->get()
            ->map(fn (Friendship $f) => (int) $f->user_id === (int) $user->id ? $f->friend_id : $f->user_id)
            ->unique()
            ->values()
            ->all();

        return User::whereIn('id', $ids)->orderBy('username')->get();
    }

    /**
     * Pending requests received by a user (they are the recipient).
     *
     * Federated rows have friend_id NULL (the local user is always user_id,
     * see the Friendship model docblock); the request direction is given by
     * is_remote_sender, so incoming federated requests must be picked up by
     * that flag instead of by friend_id.
     *
     * @return Collection<int, Friendship>
     */
    public function pendingRequests(User $user): Collection
    {
        return Friendship::with('user')
            ->where('status', Friendship::STATUS_PENDING)
            ->where(function ($q) use ($user) {
                $q->where('friend_id', $user->id)
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('user_id', $user->id)
                            ->where('is_federated', true)
                            ->where('is_remote_sender', true);
                    });
            })
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Pending requests sent by a user.
     *
     * Incoming federated requests also have user_id = the local user, so
     * they are excluded here by is_remote_sender: they belong in
     * pendingRequests(), not here.
     *
     * @return Collection<int, Friendship>
     */
    public function sentRequests(User $user): Collection
    {
        return Friendship::with('friend')
            ->where('status', Friendship::STATUS_PENDING)
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('is_federated', false)
                    ->orWhere('is_remote_sender', false);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function areFriends(int|string $userId, int|string $friendId): bool
    {
        return $this->findBetween($userId, $friendId)?->isAccepted() ?? false;
    }

    /**
     * Any friendship row between two users, in either direction.
     */
    public function findBetween(int|string $userId, int|string $friendId): ?Friendship
    {
        return Friendship::where(function ($q) use ($userId, $friendId) {
            $q->where('user_id', $userId)->where('friend_id', $friendId);
        })->orWhere(function ($q) use ($userId, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $userId);
        })->first();
    }
}
