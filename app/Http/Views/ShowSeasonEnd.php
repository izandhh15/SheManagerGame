<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Report\Services\SeasonSummaryService;
use Illuminate\Support\Facades\Log;

class ShowSeasonEnd
{
    public function __construct(
        private readonly SeasonSummaryService $seasonSummaryService,
        private readonly MatchFinalizationService $finalizationService,
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
        // Finalize any match abandoned on the live screen before summarizing
        // the season — otherwise the summary reads stale standings.
        // Best-effort: if the DB connection drops mid-transaction, log it
        // but don't take the page down with it.
        try {
            $this->finalizationService->finalizePendingIfAny($gameId);
        } catch (\Throwable $e) {
            Log::warning('ShowSeasonEnd: finalizePendingIfAny failed (non-fatal)', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
            ]);
        }

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
