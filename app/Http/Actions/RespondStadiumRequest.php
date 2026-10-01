<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Stadium\Services\NationalVenueRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The club answers a national team's stadium request (dual mode).
 *
 * Accept: the friendly moves to the club's stadium.
 * Reject: the friendly stays at the neutral fallback venue, with an excuse.
 * In both cases the national-team game gets a result notification and the
 * club-side request notification is marked as read.
 */
class RespondStadiumRequest
{
    public function __construct(
        private readonly NationalVenueRequestService $venueService,
        private readonly NotificationService $notifications,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        if ($game->isTournamentMode()) {
            abort(404);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
        ]);

        $match = GameMatch::find($matchId);

        if (! $match || $match->venue_status !== 'pending_club') {
            return redirect()->back()->with('error', __('game.venue_request_not_found'));
        }

        // The request must be addressed to the club the user manages here.
        if ($match->venue_request_team_id !== $game->team_id) {
            abort(403);
        }

        $nationalGame = Game::find($match->game_id);
        $clubTeam = $game->team;
        $accepted = $validated['decision'] === 'accept';
        // Competitive NT matches (Nations League / qualifiers) carry a money
        // offer in venue_fee; friendlies don't (null => legacy behaviour).
        $isCompetitive = $match->competition_id !== 'FRIENDLY';
        $offerEuros = $isCompetitive ? (int) ($match->venue_fee ?? 0) : 0;

        DB::transaction(function () use ($match, $accepted, $clubTeam, $game, $nationalGame, $offerEuros, $isCompetitive) {
            if ($accepted) {
                $match->neutral_venue_name = $clubTeam->stadium_name;
                $match->neutral_venue_capacity = (int) $clubTeam->stadium_seats;
                $match->venue_status = 'confirmed';

                // The club receives the offered fee: charge the federation
                // budget and credit this club game.
                if ($isCompetitive && $offerEuros > 0 && $nationalGame) {
                    $nationalGame->update([
                        'federation_budget' => max(0, (int) $nationalGame->federation_budget - $offerEuros),
                    ]);
                    \App\Models\FinancialTransaction::create([
                        'game_id' => $game->id,
                        'type' => \App\Models\FinancialTransaction::TYPE_INCOME,
                        'category' => 'venue_fee',
                        'amount' => $offerEuros * 100,
                        'description' => __('game.venue_fee_income_desc', [
                            'team' => $nationalGame->team?->name ?? '',
                            'stadium' => $clubTeam->stadium_name ?? '',
                        ]),
                        'transaction_date' => \Carbon\Carbon::parse($match->scheduled_date)->toDateString(),
                    ]);
                }
            } else {
                if ($isCompetitive) {
                    // Back to the venue-organization list: the manager can
                    // try another stadium or offer more money.
                    $match->venue_status = 'rejected';
                    $match->neutral_venue_name = null;
                    $match->neutral_venue_capacity = null;
                    $match->venue_fee = null;
                    $match->venue_request_team_id = null;
                    $match->venue_request_type = null;
                } else {
                    $match->venue_status = 'rejected';
                }
                $match->venue_request_excuse = $this->venueService->randomExcuse($clubTeam->name ?? '');
            }
            $match->save();

            // Mark the club-side request notification as read.
            GameNotification::where('game_id', $game->id)
                ->where('type', GameNotification::TYPE_STADIUM_REQUEST)
                ->where('metadata->match_id', $match->id)
                ->update(['read_at' => now()]);
        });

        // Tell the national-team side what happened.
        if ($nationalGame) {
            $this->notifications->notifyStadiumRequestResult(
                $nationalGame,
                $accepted,
                $clubTeam->stadium_name ?? '',
                $accepted ? null : $match->venue_request_excuse,
            );
        }

        return redirect()
            ->route('game.club.stadium', $gameId)
            ->with('success', $accepted
                ? __('game.venue_request_accepted', ['stadium' => $clubTeam->stadium_name ?? ''])
                : __('game.venue_request_rejected_done'));
    }
}
