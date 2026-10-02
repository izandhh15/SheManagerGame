<?php

namespace App\Http\Actions;

use App\Models\GameNotification;
use App\Modules\Government\Services\GovernmentVenueService;
use Illuminate\Http\Request;

/**
 * Accept a government venue offer (F5): pick one of the offered stadiums.
 */
class AcceptGovernmentVenue
{
    public function __construct(
        private readonly GovernmentVenueService $service,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $validated = $request->validate([
            'notification_id' => 'required|string',
            'stadium' => 'required|string',
        ]);

        $notification = GameNotification::where('game_id', $gameId)
            ->where('id', $validated['notification_id'])
            ->ofType(GameNotification::TYPE_GOVERNMENT_VENUE_OFFER)
            ->firstOrFail();

        $result = $this->service->accept($notification, $validated['stadium']);

        if (! $result['ok']) {
            return redirect()->route('game.government-venue', ['gameId' => $gameId])
                ->with('error', __($result['error'] ?? 'game.gov_venue_invalid'));
        }

        return redirect()->route('show-game', ['gameId' => $gameId])
            ->with('success', __('game.gov_venue_accepted_flash', ['stadium' => $validated['stadium']]));
    }
}
