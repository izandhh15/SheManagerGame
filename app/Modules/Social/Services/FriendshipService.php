<?php

namespace App\Modules\Social\Services;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Friend requests between users. Friendships are mutual once accepted;
 * either side can remove them. Only accepted friendships grant access to
 * a friend's careers.
 */
class FriendshipService
{
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
     * Accept a pending request. Only the recipient can accept.
     */
    public function accept(User $user, string $friendshipId): array
    {
        $friendship = Friendship::find($friendshipId);

        if (! $friendship || (int) $friendship->friend_id !== (int) $user->id) {
            return ['ok' => false, 'message' => __('friends.not_found')];
        }

        if ($friendship->isAccepted()) {
            return ['ok' => false, 'message' => __('friends.already_exists')];
        }

        $friendship->update(['status' => Friendship::STATUS_ACCEPTED]);

        return ['ok' => true, 'message' => __('friends.request_accepted')];
    }

    /**
     * Reject a pending request. Only the recipient can reject; the row is removed.
     */
    public function reject(User $user, string $friendshipId): array
    {
        $friendship = Friendship::find($friendshipId);

        if (! $friendship || (int) $friendship->friend_id !== (int) $user->id) {
            return ['ok' => false, 'message' => __('friends.not_found')];
        }

        $friendship->delete();

        return ['ok' => true, 'message' => __('friends.request_rejected')];
    }

    /**
     * Remove a friendship (or cancel a sent pending request). Either side can do it.
     */
    public function remove(User $user, string $friendshipId): array
    {
        $friendship = Friendship::find($friendshipId);

        if (! $friendship
            || ((int) $friendship->user_id !== (int) $user->id
                && (int) $friendship->friend_id !== (int) $user->id)
        ) {
            return ['ok' => false, 'message' => __('friends.not_found')];
        }

        $friendship->delete();

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
     * @return Collection<int, Friendship>
     */
    public function pendingRequests(User $user): Collection
    {
        return Friendship::with('user')
            ->where('status', Friendship::STATUS_PENDING)
            ->where('friend_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Pending requests sent by a user.
     *
     * @return Collection<int, Friendship>
     */
    public function sentRequests(User $user): Collection
    {
        return Friendship::with('friend')
            ->where('status', Friendship::STATUS_PENDING)
            ->where('user_id', $user->id)
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
