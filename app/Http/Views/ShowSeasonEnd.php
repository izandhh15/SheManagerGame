<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Report\Services\SeasonSummaryService;
use Illuminate\Support\Facades\Log;

class ShowSeasonEnd
{
    public function __construct(
        private readonly SeasonSummaryService $seasonSummaryService,
    ) {}

    public function __invoke(string $gameId)
    {
        try {
            return $this->show($gameId);
        } catch (\Throwable $e) {
            Log::error('ShowSeasonEnd failed', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('show-game', $gameId)
                ->with('error', 'Error loading season summary: ' . $e->getMessage());
        }
    }

    private function show(string $gameId)
    {
        // NOTE: finalizePendingIfAny intentionally NOT called here. At season
        // end all matches are verified played, so a stale pending flag is
        // harmless — and the transactional finalize was killing the DB
        // connection on Wasmer Edge (SQLSTATE "no connection to the server"),
        // hanging the page. The flag gets cleared on next season setup.

        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        if ($game->isTransitioningSeason()) {
            return redirect()->route('show-game', $gameId);
        }

        $unplayedMatches = $game->matches()
            ->where('played', false)
            ->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', 'Season is not complete yet.');
        }

        $data = $this->seasonSummaryService->buildSeasonSummary($game);

        return view('season-end', ['game' => $game, ...$data]);
    }
}
