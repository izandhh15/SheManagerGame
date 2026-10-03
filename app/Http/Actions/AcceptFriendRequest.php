<?php

namespace App\Http\Actions;

use App\Modules\Social\Services\FriendshipService;
use Illuminate\Http\Request;

class AcceptFriendRequest
{
    public function __construct(
        private readonly FriendshipService $friendships,
    ) {}

    public function __invoke(Request $request, string $friendshipId)
    {
        $result = $this->friendships->accept($request->user(), $friendshipId);

        return redirect()->route('friends.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
