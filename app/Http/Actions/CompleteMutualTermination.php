<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\MutualTerminationNegotiation;
use App\Models\TransferListing;
use App\Modules\Finance\Services\SeverancePaymentService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Squad\Services\SquadMinimumService;
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
        private readonly SquadMinimumService $squadMinimumService,
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

        // Revalidar la elegibilidad en el momento de completar (A8): entre
        // el acuerdo y el "completar" puede haberse aceptado una venta por
        // la jugadora, cedida a otro club o vaciado la plantilla. Si algo
        // falla, abortar SIN cobrar indemnización ni tocar la oferta.
        if ($error = $this->validateCompletion($game, $player)) {
            return redirect()->back()->with('error', $error);
        }

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

    /**
     * Revalidación TOCTOU al completar el mutuo acuerdo. Mismas guardas que
     * la liberación unilateral (ContractService::validateRelease), aplicadas
     * en el momento de completar y no solo al iniciar la negociación.
     *
     * Devuelve null si la rescisión puede completarse, o el mensaje de
     * error en caso contrario.
     */
    private function validateCompletion(Game $game, GamePlayer $player): ?string
    {
        // (a) La jugadora sigue siendo propiedad del club del usuario.
        if (!$player->isUserOwned($game)) {
            return __('messages.release_on_loan');
        }

        // (b) Sin venta acordada pendiente: una oferta AGREED aceptada entre
        // el pacto y el completar debe bloquear la rescisión (A8) para no
        // dejar la oferta huérfana en AGREED ni perder el ingreso.
        if ($player->hasAgreedTransfer()) {
            return __('messages.release_has_agreed_transfer');
        }

        // (c) Mínimo de plantilla: el roster no puede bajar del mínimo.
        $rosterTeamId = $player->isCalledUpFromReserve($game)
            ? $game->reserve_team_id
            : $player->team_id;

        $breach = $this->squadMinimumService->validateRemoval($game, $player, $rosterTeamId);
        if ($breach !== null) {
            if ($breach['type'] === 'too_small') {
                return __('messages.release_squad_too_small', ['min' => $breach['min']]);
            }

            return __('messages.release_position_minimum', [
                'group' => __('squad.' . strtolower($breach['group']) . 's'),
                'min'   => $breach['min'],
            ]);
        }

        return null;
    }
}
