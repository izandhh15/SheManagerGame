<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\MutualTerminationNegotiation;
use App\Modules\Squad\Services\SquadMinimumService;
use App\Modules\Transfer\Services\ContractService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Negociación de rescisión de contrato de mutuo acuerdo.
 *
 * El agente de la jugadora pide una indemnización (parte del salario
 * restante). El usuario puede aceptar, contraofertar o romper la negociación.
 * Si hay acuerdo, el frontend muestra el selector de forma de pago.
 */
class NegotiateMutualTermination
{
    /** Demanda inicial del agente: % del salario restante. */
    private const INITIAL_DEMAND_RATE = 0.65;

    /** A partir de este % de la demanda, el agente acepta. */
    private const ACCEPT_THRESHOLD = 0.90;

    /** Por debajo de este % de la demanda, el agente rompe. */
    private const WALK_AWAY_THRESHOLD = 0.60;

    public function __construct(
        private readonly ContractService $contractService,
        private readonly SquadMinimumService $squadMinimumService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in(['start', 'offer', 'accept', 'walk_away'])],
        ]);

        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('game_id', $gameId)
            ->whereIn('team_id', $game->userTeamIds())
            ->findOrFail($playerId);

        // Misma elegibilidad que la liberación unilateral.
        if ($error = $this->validateEligibility($game, $player)) {
            return response()->json(['status' => 'error', 'message' => $error], 422);
        }

        return match ($request->input('action')) {
            'start' => $this->handleStart($game, $player),
            'offer' => $this->handleOffer($request, $game, $player),
            'accept' => $this->handleAccept($game, $player),
            'walk_away' => $this->handleWalkAway($game, $player),
            default => response()->json(['status' => 'error', 'message' => 'Invalid action'], 400),
        };
    }

    private function handleStart(Game $game, GamePlayer $player): JsonResponse
    {
        // Reanudar negociación abierta existente.
        $existing = $this->activeNegotiation($player);
        if ($existing) {
            return $this->openResponse($game, $player, $existing, __('termination.chat_resume', [
                'player' => $player->name,
                'amount' => Money::format($existing->agent_demand),
            ]));
        }

        $remaining = $this->remainingWages($game, $player);
        $demand = (int) round($remaining * self::INITIAL_DEMAND_RATE);

        // Sin salario restante no hay nada que negociar: rescisión gratis.
        if ($demand <= 0) {
            $negotiation = MutualTerminationNegotiation::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'status' => MutualTerminationNegotiation::STATUS_AGREED,
                'round' => 0,
                'agent_demand' => 0,
                'agreed_amount' => 0,
            ]);

            $paymentMethods = app(\App\Modules\Finance\Services\SeverancePaymentService::class)
                ->availableMethods($game, 0);

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'agreed',
                'agreed_amount' => 0,
                'agreed_amount_formatted' => Money::format(0),
                'message' => __('termination.chat_free', ['player' => $player->name]),
                'payment_methods' => array_map(fn ($m) => [
                    'key' => $m['key'],
                    'label' => $m['label'],
                    'detail' => $m['detail'],
                ], $paymentMethods),
                'complete_url' => route('game.squad.mutual-termination.complete', [$game->id, $player->id]),
            ]);
        }

        $negotiation = MutualTerminationNegotiation::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'status' => MutualTerminationNegotiation::STATUS_OPEN,
            'round' => 0,
            'agent_demand' => $demand,
        ]);

        return $this->openResponse($game, $player, $negotiation, __('termination.chat_initial_demand', [
            'player' => $player->name,
            'amount' => Money::format($demand),
        ]));
    }

    private function handleOffer(Request $request, Game $game, GamePlayer $player): JsonResponse
    {
        $validated = $request->validate([
            // 32-bit: keep the *100 to cents within exact float/integer range;
            // euros are capped at 99999999.
            'amount' => ['required', 'integer', 'min:0', 'max:99999999'], // euros
        ]);

        $negotiation = $this->activeNegotiation($player);
        if (!$negotiation) {
            return response()->json(['status' => 'error', 'message' => __('termination.no_active_negotiation')], 422);
        }

        $offerCents = $validated['amount'] * 100;
        $demand = (int) $negotiation->agent_demand;
        $round = $negotiation->round + 1;

        $negotiation->update(['user_offer' => $offerCents, 'round' => $round]);

        // El agente acepta si la oferta se acerca a su demanda.
        if ($offerCents >= $demand * self::ACCEPT_THRESHOLD) {
            return $this->agree($game, $player, $negotiation, $offerCents, __('termination.chat_agent_accepts_offer', [
                'player' => $player->name,
                'amount' => Money::format($offerCents),
            ]));
        }

        // Oferta insultante o sin rondas: rompe la negociación.
        if ($offerCents < $demand * self::WALK_AWAY_THRESHOLD || $round >= MutualTerminationNegotiation::MAX_ROUNDS) {
            $negotiation->update(['status' => MutualTerminationNegotiation::STATUS_REJECTED]);

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'message' => __('termination.chat_agent_walks_away', ['player' => $player->name]),
            ]);
        }

        // Contraoferta: punto medio entre oferta y demanda, redondeado.
        $counter = (int) (round(($offerCents + $demand) / 2 / 10000) * 10000);
        $negotiation->update(['agent_demand' => $counter]);

        return $this->openResponse($game, $player, $negotiation, __('termination.chat_agent_counters', [
            'player' => $player->name,
            'amount' => Money::format($counter),
        ]));
    }

    private function handleAccept(Game $game, GamePlayer $player): JsonResponse
    {
        $negotiation = $this->activeNegotiation($player);
        if (!$negotiation) {
            return response()->json(['status' => 'error', 'message' => __('termination.no_active_negotiation')], 422);
        }

        return $this->agree($game, $player, $negotiation, (int) $negotiation->agent_demand, __('termination.chat_agreed', [
            'player' => $player->name,
            'amount' => Money::format($negotiation->agent_demand),
        ]));
    }

    private function handleWalkAway(Game $game, GamePlayer $player): JsonResponse
    {
        $negotiation = $this->activeNegotiation($player);
        $negotiation?->update(['status' => MutualTerminationNegotiation::STATUS_WALKED_AWAY]);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'walked_away',
            'message' => __('termination.chat_you_walked_away', ['player' => $player->name]),
        ]);
    }

    private function agree(Game $game, GamePlayer $player, MutualTerminationNegotiation $negotiation, int $amount, string $message): JsonResponse
    {
        $negotiation->update([
            'status' => MutualTerminationNegotiation::STATUS_AGREED,
            'agreed_amount' => $amount,
        ]);

        $paymentMethods = app(\App\Modules\Finance\Services\SeverancePaymentService::class)
            ->availableMethods($game, $amount);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'agreed',
            'agreed_amount' => $amount,
            'agreed_amount_formatted' => Money::format($amount),
            'message' => $message,
            'payment_methods' => array_map(fn ($m) => [
                'key' => $m['key'],
                'label' => $m['label'],
                'detail' => $m['detail'],
            ], $paymentMethods),
            'complete_url' => route('game.squad.mutual-termination.complete', [$game->id, $player->id]),
        ]);
    }

    private function openResponse(Game $game, GamePlayer $player, MutualTerminationNegotiation $negotiation, string $message): JsonResponse
    {
        $unilateral = $this->contractService->calculateSeverance($game, $player);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'open',
            'round' => $negotiation->round,
            'max_rounds' => MutualTerminationNegotiation::MAX_ROUNDS,
            'agent_demand' => (int) $negotiation->agent_demand,
            'agent_demand_formatted' => Money::format($negotiation->agent_demand),
            'unilateral_cost' => $unilateral,
            'unilateral_cost_formatted' => Money::format($unilateral),
            'player_name' => $player->name,
            'message' => $message,
        ]);
    }

    private function activeNegotiation(GamePlayer $player): ?MutualTerminationNegotiation
    {
        return MutualTerminationNegotiation::where('game_player_id', $player->id)
            ->where('status', MutualTerminationNegotiation::STATUS_OPEN)
            ->first();
    }

    private function remainingWages(Game $game, GamePlayer $player): int
    {
        if (!$player->contract_until || !$player->annual_wage) {
            return 0;
        }

        $remainingYears = max(0, $game->current_date->diffInYears($player->contract_until, true));

        return (int) ($player->annual_wage * $remainingYears);
    }

    private function validateEligibility(Game $game, GamePlayer $player): ?string
    {
        if (!in_array($player->team_id, $game->userTeamIds(), true)) {
            return __('messages.release_not_your_player');
        }

        if (!$player->isUserOwned($game)) {
            return __('messages.release_on_loan');
        }

        if ($player->hasAgreedTransfer() || $player->hasPreContractAgreement()) {
            return __('messages.release_has_agreed_transfer');
        }

        // M4: mínimo de plantilla también al INICIAR la negociación (no solo
        // al completar). Misma guarda que la liberación unilateral
        // (ContractService::validateRelease): el roster no puede bajar de 17.
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
