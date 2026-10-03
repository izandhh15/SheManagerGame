<?php

namespace App\Http\Actions;

use App\Modules\Social\Services\FederationService;
use Illuminate\Http\Request;

/**
 * Receive a federated friend request from the peer instance.
 * Signed with HMAC-SHA256 (X-Federation-Timestamp / X-Federation-Signature).
 */
class FederationFriendRequest
{
    public function __construct(
        private readonly FederationService $federation,
    ) {}

    public function __invoke(Request $request)
    {
        if (! $this->federation->enabled() || ! $this->federation->verifyRequest($request)) {
            abort(403, 'Invalid federation signature.');
        }

        $validated = $request->validate([
            'from_username' => 'required|string|max:255',
            'from_user_id' => 'required|integer',
            'from_club' => 'nullable|string|max:255',
            'from_request_uuid' => 'required|string|max:36',
            'from_instance' => 'required|string|max:255',
            'to_username' => 'required|string|max:255',
        ]);

        $result = $this->federation->handleIncomingRequest($validated);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error']], 404);
        }

        return response()->json(['ok' => true, 'request_uuid' => $result['request_uuid']]);
    }
}
