<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\MutualTerminationNegotiation;
use App\Models\TransferListing;
use App\Modules\Finance\Services\SeverancePaymentService;
use App\Modules\Notification\Services\NotificationService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Completa la rescisión de mutuo acuerdo: libera a la jugadora y paga
 * la indemnización pactada con la forma de pago elegida.
 */
class CompleteMutualTermination
{
    public function __construct(
        private readonly SeverancePaymentService $severancePaymentService,
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', Rule::in([
                SeverancePaymentService::METHOD_LUMP_SUM,
                SeverancePaymentService::METHOD_INSTALLMENTS_6,
                SeverancePaymentService::METHOD_INSTALLMENTS_12,
                SeverancePaymentService::METHOD_BANK_LOAN,
            ])],
        ]);

        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('id', $playerId)
            ->where('game_id', $gameId)
            ->whereIn('team_id', $game->userTeamIds())
            ->firstOrFail();

        $negotiation = MutualTerminationNegotiation::where('game_player_id', $player->id)
            ->where('status', MutualTerminationNegotiation::STATUS_AGREED)
            ->first();

        if (!$negotiation) {
            return redirect()->back()->with('error', __('termination.no_agreement'));
        }

        $playerName = $player->name;
        $amount = (int) $negotiation->agreed_amount;

        // Pagar con el método elegido.
        $result = $this->severancePaymentService->paySeverance(
            $game,
            $player,
            $playerName,
            $amount,
            $validated['payment_method'],
        );

        if ($result['error'] ?? false) {
            return redirect()->back()->with('error', $result['error']);
        }

        // Liberar a la jugadora (igual que releasePlayer pero sin recalcular).
        TransferListing::where('game_player_id', $player->id)->delete();
        $player->update([
            'team_id' => null,
            'number' => null,
            'release_clause' => null,
        ]);

        $activeNegotiation = $player->activeRenewalNegotiation;
        if ($activeNegotiation) {
            $activeNegotiation->update(['status' => \App\Models\RenewalNegotiation::STATUS_EXPIRED]);
        }

        $negotiation->update(['status' => MutualTerminationNegotiation::STATUS_COMPLETED]);

        $this->notificationService->create(
            game: $game,
            type: \App\Models\GameNotification::TYPE_PLAYER_RELEASED,
            title: __('notifications.mutual_termination_title', ['player' => $playerName]),
            message: __('notifications.mutual_termination_message', [
                'player' => $playerName,
                'amount' => Money::format($amount),
            ]),
            priority: \App\Models\GameNotification::PRIORITY_INFO,
        );

        return redirect()
            ->back()
            ->with('success', __('messages.mutual_termination_completed', [
                'player' => $playerName,
                'amount' => Money::format($amount),
            ]));
    }
}
