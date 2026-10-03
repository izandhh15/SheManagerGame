<?php

namespace App\Http\Actions;

use App\Modules\Social\Services\FriendshipService;
use Illuminate\Http\Request;

class SendFriendRequest
{
    public function __construct(
        private readonly FriendshipService $friendships,
    ) {}

    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255',
        ]);

        $result = $this->friendships->sendRequest($request->user(), $validated['username']);

        return redirect()->route('friends.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
