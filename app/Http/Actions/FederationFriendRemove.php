<?php

namespace App\Http\Actions;

use App\Modules\Social\Services\FederationService;
use Illuminate\Http\Request;

/**
 * The peer notifies us that the other side removed an accepted federated
 * friendship. We mirror the removal locally.
 */
class FederationFriendRemove
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
            'request_uuid' => 'required|string|max:36',
        ]);

        if (! $this->federation->handlePeerRemove($validated['request_uuid'])) {
            return response()->json(['ok' => false, 'error' => 'friendship_not_found'], 404);
        }

        return response()->json(['ok' => true]);
    }
}
