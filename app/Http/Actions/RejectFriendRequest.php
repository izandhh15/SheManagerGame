<?php

namespace App\Http\Actions;

use App\Modules\Social\Services\FriendshipService;
use Illuminate\Http\Request;

class RejectFriendRequest
{
    public function __construct(
        private readonly FriendshipService $friendships,
    ) {}

    public function __invoke(Request $request, string $friendshipId)
    {
        $result = $this->friendships->reject($request->user(), $friendshipId);

        return redirect()->route('friends.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
