<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Media\Services\NationalSocialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnounceNationalPost
{
    public function __construct(
        private readonly NationalSocialService $nationalSocial,
    ) {}

    public function __invoke(Request $request, string $gameId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        if ($game->team?->type !== 'national') {
            abort(404);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', NationalSocialService::TYPES)],
            'tier' => ['nullable', 'string', 'in:' . implode(',', array_keys(NationalSocialService::TICKET_TIERS))],
        ]);

        $result = $this->nationalSocial->announce(
            $game,
            $validated['type'],
            ['tier' => $validated['tier'] ?? null],
        );

        return redirect()->route('game.national-social', $gameId)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
