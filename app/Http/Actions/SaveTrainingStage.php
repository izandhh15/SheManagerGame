<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Season\Services\TrainingStageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaveTrainingStage
{
    public function __construct(
        private readonly TrainingStageService $stageService,
    ) {}

    public function __invoke(Request $request, string $gameId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        if (! $game->isTournamentMode()) {
            abort(404);
        }

        $validated = $request->validate([
            'destination' => ['required', 'string', 'max:100'],
            'duration' => ['required', 'string', 'in:1w,2w'],
            'intensity' => ['required', 'string', 'in:light,balanced,intense'],
            'focus' => ['required', 'string', 'in:physical,tactical,youth'],
        ]);

        $result = $this->stageService->confirmStage($game, $validated);

        if (! $result['ok']) {
            return redirect()->route('game.schedule-friendly', $gameId)
                ->with('error', $result['message']);
        }

        $message = $result['message'];
        if (! empty($result['injured'])) {
            $message .= ' ' . __('game.stage_injuries_report', [
                'names' => implode(', ', $result['injured']),
            ]);
        }

        return redirect()->route('game.schedule-friendly', $gameId)
            ->with('success', $message);
    }
}
