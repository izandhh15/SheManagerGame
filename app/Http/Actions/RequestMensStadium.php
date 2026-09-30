<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Stadium\Services\MensStadiumRequestService;
use Illuminate\Http\Request;

class RequestMensStadium
{
    public function __construct(
        private readonly MensStadiumRequestService $mensStadiumService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $validated = $request->validate([
            'match_id' => 'required|string',
        ]);

        $match = GameMatch::where('game_id', $game->id)
            ->where('id', $validated['match_id'])
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->firstOrFail();

        $teamName = $game->team?->name ?? '';
        $mens = $this->mensStadiumService->mensStadiumFor($teamName);

        if ($mens === null) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __('game.mens_stadium_not_available'));
        }

        if (! $this->mensStadiumService->canRequest($game)) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __('game.mens_stadium_limit_reached', [
                    'max' => MensStadiumRequestService::MAX_PER_SEASON,
                ]));
        }

        if ($match->neutral_venue_name !== null) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __('game.mens_stadium_already_set'));
        }

        $result = $this->mensStadiumService->requestForMatch($match, $game);

        if ($result['accepted']) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('success', __('game.mens_stadium_accepted', [
                    'stadium' => $mens['stadium'],
                    'opponent' => $match->awayTeam?->name ?? '',
                ]));
        }

        $reasonKey = 'game.mens_stadium_rejected_' . ($result['reasons'][0] ?? 'generic');

        return redirect()->route('game.club.stadium', ['gameId' => $gameId])
            ->with('error', __($reasonKey, ['stadium' => $mens['stadium']]));
    }
}
