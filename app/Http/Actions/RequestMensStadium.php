<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Stadium\Services\MensStadiumRequestService;
use Illuminate\Http\Request;

/**
 * Step 1 of the men's-stadium rental: the user picks a ground from the
 * catalogue and the owner names their price. The quote is stored in the
 * session (persistent, not flashed: it must survive the quote page GET and
 * the later confirm POST); nothing is charged or moved until
 * ConfirmMensStadium.
 */
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
            'stadium' => 'required|string',
        ]);

        $match = GameMatch::where('game_id', $game->id)
            ->where('id', $validated['match_id'])
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->firstOrFail();

        $stadium = $this->mensStadiumService->stadiumByKey($validated['stadium']);

        if ($stadium === null) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __('game.mens_stadium_not_available'));
        }

        $teamName = $game->team?->name ?? '';

        // Restricted rental: a club with a mapped men's stadium can only
        // ever rent that one ground. The UI shows a single option, but a
        // crafted POST could name any catalogue key — reject it here.
        if (! $this->mensStadiumService->isRentableBy($teamName, $stadium['key'])) {
            $mine = $this->mensStadiumService->mensStadiumFor($teamName);

            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __('game.mens_stadium_not_yours', [
                    'stadium' => $stadium['stadium'],
                    'mine' => $mine['stadium'] ?? '',
                ]));
        }

        if ($match->neutral_venue_name !== null) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __('game.mens_stadium_already_set'));
        }

        $quote = $this->mensStadiumService->quoteForMatch($match, $game, $stadium);

        if (! $quote['eligible'] || ! $quote['accepted']) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', $this->rejectionMessage($quote, $stadium, $teamName));
        }

        $request->session()->put('mens_quote', [
            'match_id' => $match->id,
            'key' => $stadium['key'],
            'stadium' => $stadium['stadium'],
            'capacity' => $stadium['capacity'],
            'price' => $quote['price'],
            'affiliated' => $quote['affiliated'],
            'casa_invita' => $quote['casa_invita'],
            'importance' => $quote['importance'],
            'owner' => $stadium['owner'] ?? $stadium['club'],
            'opponent' => $match->awayTeam?->name ?? '',
        ]);

        return redirect()->route('game.club.stadium', ['gameId' => $gameId]);
    }

    /**
     * Owner-aware refusal message: the affiliated men's club gets the
     * detailed excuses; other owners (another club, a city council) get a
     * generic "not important enough" line with their name.
     */
    private function rejectionMessage(array $quote, array $stadium, string $teamName): string
    {
        $reason = $quote['reasons'][0] ?? 'generic';
        $affiliated = $this->mensStadiumService->isAffiliated($stadium, $teamName);
        $params = [
            'stadium' => $stadium['stadium'],
            'owner' => $stadium['owner'] ?? $stadium['club'] ?? $stadium['stadium'],
            'max' => MensStadiumRequestService::MAX_PER_SEASON,
        ];

        if ($reason === 'limit_reached' || $reason === 'too_late' || $reason === 'no_mens_stadium') {
            return __('game.mens_stadium_rejected_' . $reason, $params);
        }

        if (! $affiliated) {
            return __('game.mens_stadium_rejected_owner_generic', $params);
        }

        return __('game.mens_stadium_rejected_' . $reason, $params);
    }
}
