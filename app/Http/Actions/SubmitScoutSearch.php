<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Transfer\Services\ScoutingService;
use App\Support\PositionMapper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubmitScoutSearch
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Check no search currently in progress
        $searching = $this->scoutingService->getActiveReport($game);
        if ($searching) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', __('messages.scout_already_searching'));
        }

        // Check search history cap
        if ($this->scoutingService->isSearchHistoryFull($game)) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', __('messages.scout_search_limit', ['max' => ScoutingService::MAX_SEARCH_HISTORY]));
        }

        $validated = $request->validate([
            // Position must be one of the game's real scout filters
            // (group keys gk/def/mid/fwd, any_* groups, or slot codes).
            'position' => ['required', 'string', Rule::in(array_merge(
                ['gk', 'def', 'mid', 'fwd', 'any_defender', 'any_midfielder', 'any_forward'],
                array_keys(PositionMapper::getFilterOptions())
            ))],
            'scope' => 'nullable|array',
            'scope.*' => 'in:domestic,international',
            'age_min' => 'nullable|integer|min:16|max:45',
            'age_max' => 'nullable|integer|min:16|max:45',
            'ability_min' => 'nullable|integer|min:1|max:99',
            'ability_max' => 'nullable|integer|min:1|max:99',
            'value_min' => 'nullable|integer|min:0',
            'value_max' => 'nullable|integer|min:0',
            'expiring_contract' => 'nullable|boolean',
        ]);

        $filters = [
            'position' => $validated['position'],
            'scope' => $validated['scope'] ?? ['domestic', 'international'],
            'age_min' => $validated['age_min'] ?? null,
            'age_max' => $validated['age_max'] ?? null,
            'ability_min' => $validated['ability_min'] ?? null,
            'ability_max' => $validated['ability_max'] ?? null,
            'value_min' => $validated['value_min'] ?? null,
            'value_max' => $validated['value_max'] ?? null,
            'expiring_contract' => !empty($validated['expiring_contract']),
        ];

        // startSearch() re-checks "no active search" under a lock: if a
        // concurrent request won the race, it throws instead of starting a
        // second search — surface the same friendly error as the guard above.
        try {
            $this->scoutingService->startSearch($game, $filters);
        } catch (\DomainException $e) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', __($e->getMessage()));
        }

        return redirect()->route('game.scouting', $gameId)
            ->with('success', __('messages.scout_search_started'));
    }
}
