<?php

namespace App\Http\Actions;

use App\Modules\Social\Services\FederationService;
use Illuminate\Http\Request;

class SendFederatedFriendRequest
{
    public function __construct(
        private readonly FederationService $federation,
    ) {}

    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255',
        ]);

        $result = $this->federation->sendRequest($request->user(), $validated['username']);

        return redirect()->route('friends.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
