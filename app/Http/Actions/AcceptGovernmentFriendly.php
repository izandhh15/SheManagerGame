<?php

namespace App\Http\Actions;

use App\Models\GameNotification;
use App\Modules\Government\Services\GovernmentFriendlyService;
use Illuminate\Http\Request;

/**
 * Accept a government-paid friendly offer (F4).
 */
class AcceptGovernmentFriendly
{
    public function __construct(
        private readonly GovernmentFriendlyService $service,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $validated = $request->validate([
            'notification_id' => 'required|string',
        ]);

        $notification = GameNotification::where('game_id', $gameId)
            ->where('id', $validated['notification_id'])
            ->ofType(GameNotification::TYPE_GOVERNMENT_FRIENDLY_OFFER)
            ->firstOrFail();

        $result = $this->service->accept($notification);

        if (! $result['ok']) {
            return redirect()->route('game.government-friendly', ['gameId' => $gameId])
                ->with('error', __($result['error'] ?? 'game.gov_friendly_invalid'));
        }

        return redirect()->route('show-game', ['gameId' => $gameId])
            ->with('success', __('game.gov_friendly_accepted_flash'));
    }
}
