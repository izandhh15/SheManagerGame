<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Stadium\Services\MensStadiumRequestService;
use Illuminate\Http\Request;

/**
 * Step 2 of the men's-stadium rental: the user accepts the owner's price.
 * The quote stored in the session by RequestMensStadium is re-validated
 * against the posted values; the price is re-checked against the club
 * budget before charging.
 */
class ConfirmMensStadium
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

        $quote = $request->session()->get('mens_quote');

        if (! is_array($quote)
            || ($quote['match_id'] ?? null) !== $validated['match_id']
            || ($quote['key'] ?? null) !== $validated['stadium']
        ) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __('game.mens_stadium_quote_expired'));
        }

        $match = GameMatch::where('game_id', $game->id)
            ->where('id', $validated['match_id'])
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->whereNull('neutral_venue_name')
            ->first();

        if ($match === null) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __('game.mens_stadium_quote_expired'));
        }

        // The quote is stale if the match got too close in the meantime.
        if (! $this->mensStadiumService->hasEnoughAdvance($match, $game)) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __('game.mens_stadium_rejected_too_late', ['stadium' => $quote['stadium']]));
        }

        $stadium = $this->mensStadiumService->stadiumByKey($validated['stadium']);
        if ($stadium === null) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __('game.mens_stadium_not_available'));
        }

        $result = $this->mensStadiumService->confirmQuote($match, $game, [
            'price' => $quote['price'],
            'stadium' => $stadium,
        ]);

        if (! $result['ok']) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('error', __($result['error'] ?? 'game.mens_stadium_quote_expired'));
        }

        $request->session()->forget('mens_quote');

        if ((int) $quote['price'] > 0) {
            return redirect()->route('game.club.stadium', ['gameId' => $gameId])
                ->with('success', __('game.mens_stadium_rented', [
                    'stadium' => $quote['stadium'],
                    'price' => number_format((int) $quote['price'], 0, ',', '.'),
                    'opponent' => $match->awayTeam?->name ?? '',
                ]));
        }

        return redirect()->route('game.club.stadium', ['gameId' => $gameId])
            ->with('success', __('game.mens_stadium_accepted', [
                'stadium' => $quote['stadium'],
                'opponent' => $match->awayTeam?->name ?? '',
            ]));
    }
}
