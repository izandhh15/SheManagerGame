<?php

namespace App\Http\Actions;

use App\Modules\Social\Services\FederationService;
use Illuminate\Http\Request;

/**
 * The peer notifies us that OUR outgoing request was accepted there.
 * {request_uuid} is OUR friendship row id (stored by the peer as
 * remote_request_uuid when the request arrived).
 */
class FederationFriendAccept
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

        if (! $this->federation->handlePeerAccept($validated['request_uuid'])) {
            return response()->json(['ok' => false, 'error' => 'request_not_found'], 404);
        }

        return response()->json(['ok' => true]);
    }
}
