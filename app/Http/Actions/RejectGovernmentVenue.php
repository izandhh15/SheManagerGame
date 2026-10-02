<?php

namespace App\Http\Actions;

use App\Models\GameNotification;
use App\Modules\Government\Services\GovernmentVenueService;
use Illuminate\Http\Request;

/**
 * Reject a government venue offer (F5).
 */
class RejectGovernmentVenue
{
    public function __construct(
        private readonly GovernmentVenueService $service,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $validated = $request->validate([
            'notification_id' => 'required|string',
        ]);

        $notification = GameNotification::where('game_id', $gameId)
            ->where('id', $validated['notification_id'])
            ->ofType(GameNotification::TYPE_GOVERNMENT_VENUE_OFFER)
            ->firstOrFail();

        $this->service->reject($notification);

        return redirect()->route('show-game', ['gameId' => $gameId])
            ->with('success', __('game.gov_venue_rejected_flash'));
    }
}
