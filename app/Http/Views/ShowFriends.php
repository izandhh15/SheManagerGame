<?php

namespace App\Http\Views;

use App\Modules\Social\Services\FriendshipService;
use Illuminate\Http\Request;

class ShowFriends
{
    public function __construct(
        private readonly FriendshipService $friendships,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Accepted friendships with the *other* user attached, so the view
        // can link to careers and remove without extra queries.
        $friendships = \App\Models\Friendship::with(['user', 'friend'])
            ->where('status', \App\Models\Friendship::STATUS_ACCEPTED)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('friend_id', $user->id))
            ->get()
            ->map(fn ($f) => [
                'friendship' => $f,
                'other' => (int) $f->user_id === (int) $user->id ? $f->friend : $f->user,
            ])
            ->sortBy(fn ($row) => $row['other']->username)
            ->values();

        return view('friends.index', [
            'friendships' => $friendships,
            'pending' => $this->friendships->pendingRequests($user),
            'sent' => $this->friendships->sentRequests($user),
        ]);
    }
}
