<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Modules\Finance\Services\SalaryCapService;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\DispositionService;
use App\Modules\Transfer\Services\RenewalRivalOfferService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NegotiateRenewal
{
    private const MAX_ROUNDS = ContractService::MAX_NEGOTIATION_ROUNDS;

    public function __construct(
        private readonly ContractService $contractService,
        private readonly DispositionService $dispositionService,
        private readonly SalaryCapService $salaryCapService,
        private readonly RenewalRivalOfferService $rivalOfferService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in(['start', 'offer', 'accept_counter'])],
        ]);

        $game = Game::findOrFail($gameId);

        $action = $request->input('action');
        $eagerLoads = in_array($action, ['start', 'offer'])
            ? ['game', 'transferOffers']
            : ['game'];

        $player = GamePlayer::with($eagerLoads)
            ->where('game_id', $gameId)
            ->userOwned($game)
            ->findOrFail($playerId);

        return match ($action) {
            'start' => $this->handleStart($game, $player),
            'offer' => $this->handleOffer($request, $game, $player),
            'accept_counter' => $this->handleAcceptCounter($game, $player),
            default => response()->json(['status' => 'error', 'message' => 'Invalid action'], 400),
        };
    }

    private function handleStart(Game $game, GamePlayer $player): JsonResponse
    {
        // Check if there's an existing countered negotiation to resume
        $existing = RenewalNegotiation::where('game_player_id', $player->id)
            ->where('status', RenewalNegotiation::STATUS_PLAYER_COUNTERED)
            ->first();

        if ($existing) {
            $disposition = $this->contractService->calculateDisposition($player, NegotiationScenario::RENEWAL, round: $existing->round);
            $mood = $this->contractService->getMoodIndicator($disposition);

            // A rival may enter the picture while the player holds a counter.
            $this->rivalOfferService->maybeEscalateRivalOffer($game, $player, $existing);
            $existing->refresh();

            return response()->json(array_merge($this->contractService->releaseClausePayload($game, $player, (int) $existing->player_demand), [
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $existing->round,
                'max_rounds' => self::MAX_ROUNDS,
                'wage_floor' => (int) ($this->contractService->getMinimumWageForTeam($game->team) / 100),
                'messages' => [
                    $this->agentMessage('counter', array_merge([
                        'text' => $this->resumeText($player, $existing),
                        'wage' => (int) ($existing->counter_offer / 100),
                        'years' => $existing->preferred_years,
                        'mood' => $mood,
                    ], $this->rivalOfferContent($existing)), [
                        'canAccept' => true,
                        'suggestedWage' => $this->calculateMidpointInEuros($existing->user_offer, $existing->counter_offer),
                        'preferredYears' => $existing->preferred_years,
                    ]),
                ],
            ]));
        }

        // Cooldown: must wait at least one matchday after a rejected negotiation
        if (RenewalNegotiation::hasRenewalCooldown($player->id, $game->current_date)) {
            return response()->json([
                'status' => 'error',
                'message' => __('transfers.renewal_cooldown'),
            ], 422);
        }

        // New negotiation — show player demand
        if (!$player->canBeOfferedRenewal(currentDate: $game->current_date)) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.cannot_renew'),
            ], 422);
        }

        // Player with plenty of contract left and good morale won't engage in talks.
        if (!$this->dispositionService->isWillingToNegotiateRenewal($player)) {
            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'round' => 0,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('rejected', [
                        'text' => __('transfers.chat_agent_not_interested', [
                            'player' => $player->name,
                        ]),
                    ]),
                ],
            ]);
        }

        $demand = $this->contractService->calculateWageDemand($player, NegotiationScenario::RENEWAL);
        $disposition = $this->contractService->calculateDisposition($player, NegotiationScenario::RENEWAL);
        $mood = $this->contractService->getMoodIndicator($disposition);
        $wageFloorEuros = (int) ($this->contractService->getMinimumWageForTeam($game->team) / 100);

        // Prepare the negotiation row now: the agent may already arrive with a
        // rival club's offer on the table.
        $negotiation = $this->contractService->prepareNegotiation($player);

        return response()->json(array_merge($this->contractService->releaseClausePayload($game, $player, (int) $demand['wage']), [
            'status' => 'ok',
            'negotiation_status' => 'open',
            'round' => 0,
            'max_rounds' => self::MAX_ROUNDS,
            'wage_floor' => $wageFloorEuros,
            'messages' => [
                $this->agentMessage('demand', array_merge([
                    'text' => $this->demandText($player, $demand, $negotiation),
                    'wage' => (int) ($demand['wage'] / 100),
                    'years' => $demand['contractYears'],
                    'mood' => $mood,
                ], $this->rivalOfferContent($negotiation)), [
                    'canAccept' => true,
                    'suggestedWage' => (int) ($demand['wage'] / 100),
                    'preferredYears' => $demand['contractYears'],
                ]),
            ],
        ]));
    }

    private function handleOffer(Request $request, Game $game, GamePlayer $player): JsonResponse
    {
        $validated = $request->validate([
            'wage' => ['required', 'integer', 'min:1'],
            'years' => ['required', 'integer', 'min:1', 'max:5'],
            'clause' => ['nullable', 'integer', 'min:0'],
        ]);

        $offerWageEuros = $validated['wage'];
        $offeredYears = $validated['years'];
        $offerWageCents = $offerWageEuros * 100;

        $requestedClauseCents = $this->contractService->resolveRequestedClauseCents($validated['clause'] ?? null, $game);

        // Salary cap: a renewal replaces the player's current wage, so only the
        // increase is charged against the cap. Wage cuts always pass.
        $freedWage = $this->salaryCapService->effectiveWageFor($player);
        if (! $this->salaryCapService->canCommitWage($game, $offerWageCents, $freedWage)) {
            return response()->json([
                'status' => 'error',
                'message' => $this->salaryCapService->blockMessage($game, $player->name, $offerWageCents, $freedWage),
            ], 422);
        }

        $result = $this->contractService->negotiateSync($player, $offerWageCents, $offeredYears, $requestedClauseCents);
        $negotiation = $result['negotiation'];

        // A rival may smell blood if the user keeps lowballing.
        if ($result['result'] !== 'accepted') {
            $this->rivalOfferService->maybeEscalateRivalOffer($game, $player, $negotiation);
            $negotiation->refresh();
        }

        return match ($result['result']) {
            'accepted' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'accepted',
                'round' => $negotiation->round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('accepted', [
                        'text' => __('transfers.chat_agent_accepted', [
                            'player' => $player->name,
                            'wage' => Money::format($negotiation->user_offer),
                            'years' => $negotiation->contract_years,
                        ]),
                        'wage' => (int) ($negotiation->user_offer / 100),
                        'years' => $negotiation->contract_years,
                    ]),
                ],
            ]),
            'countered' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $negotiation->round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', array_merge([
                        'text' => $this->counterText($player, $negotiation),
                        'wage' => (int) ($negotiation->counter_offer / 100),
                        'years' => $negotiation->preferred_years,
                        'mood' => $this->contractService->getMoodIndicator($negotiation->disposition),
                    ], $this->rivalOfferContent($negotiation)), [
                        'canAccept' => true,
                        'suggestedWage' => $this->calculateMidpointInEuros($negotiation->user_offer, $negotiation->counter_offer),
                        'preferredYears' => $negotiation->preferred_years,
                    ]),
                ],
            ]),
            default => $this->rejectedResponse($game, $player, $negotiation),
        };
    }

    /**
     * Build the rejection response. If a rival offer was on the table, the
     * player walks straight into the rival's arms: special message plus a
     * notification registering the rival's interest.
     */
    private function rejectedResponse(Game $game, GamePlayer $player, RenewalNegotiation $negotiation): JsonResponse
    {
        if ($negotiation->hasActiveRivalOffer()) {
            $negotiation->loadMissing('rivalTeam');
            $rivalName = $negotiation->rivalTeam?->name ?? __('transfers.chat_rival_offer_title');

            \App\Models\GameNotification::create([
                'game_id' => $game->id,
                'type' => \App\Models\GameNotification::TYPE_TRANSFER_FAILED,
                'title' => __('transfers.chat_deal_failed'),
                'message' => __('transfers.chat_agent_rival_rejected', [
                    'player' => $player->name,
                    'rival' => $rivalName,
                    'wage' => Money::format($negotiation->rival_offer_wage),
                    'years' => $negotiation->rival_offer_years,
                ]),
                'icon' => 'transfer',
                'priority' => 'high',
                'metadata' => [
                    'player_id' => $player->id,
                    'rival_team_id' => $negotiation->rival_team_id,
                    'rival_offer_wage' => $negotiation->rival_offer_wage,
                ],
                'game_date' => $game->current_date,
            ]);

            $text = __('transfers.chat_agent_rival_rejected', [
                'player' => $player->name,
                'rival' => $rivalName,
                'wage' => Money::format($negotiation->rival_offer_wage),
                'years' => $negotiation->rival_offer_years,
            ]);
        } else {
            $text = __('transfers.chat_agent_rejected', ['player' => $player->name]);
        }

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'rejected',
            'round' => $negotiation->round,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('rejected', array_merge(
                    ['text' => $text],
                    $this->rivalOfferContent($negotiation),
                )),
            ],
        ]);
    }

    /**
     * Demand text for the opening message, with the rival card when present.
     */
    private function demandText(GamePlayer $player, array $demand, RenewalNegotiation $negotiation): string
    {
        $text = __('transfers.chat_agent_demand', [
            'player' => $player->name,
            'wage' => $demand['formattedWage'],
            'years' => $demand['contractYears'],
        ]);

        if ($negotiation->hasActiveRivalOffer()) {
            $negotiation->loadMissing('rivalTeam');
            $text .= ' ' . __('transfers.chat_agent_rival_offer', [
                'rival' => $negotiation->rivalTeam?->name ?? '',
                'player' => $player->name,
                'wage' => Money::format($negotiation->rival_offer_wage),
                'years' => $negotiation->rival_offer_years,
            ]);
        }

        return $text;
    }

    /**
     * Counter text: base counter plus patience warnings and rival pressure.
     */
    private function counterText(GamePlayer $player, RenewalNegotiation $negotiation): string
    {
        $text = __('transfers.chat_agent_counter', [
            'player' => $player->name,
            'wage' => Money::format($negotiation->counter_offer),
            'years' => $negotiation->preferred_years,
        ]);

        $patience = (int) ($negotiation->agent_patience ?? 100);
        if ($patience < 40) {
            $text .= ' ' . __('transfers.chat_agent_patience_low');
        } elseif ($patience < 70) {
            $text .= ' ' . __('transfers.chat_agent_impatient');
        }

        if ($negotiation->hasActiveRivalOffer()) {
            $negotiation->loadMissing('rivalTeam');
            $rivalName = $negotiation->rivalTeam?->name ?? '';
            // If the user already matched the rival wage, acknowledge it but
            // push for a little more; otherwise apply full pressure.
            if ((int) $negotiation->user_offer >= (int) $negotiation->rival_offer_wage) {
                $text .= ' ' . __('transfers.chat_agent_rival_match', ['rival' => $rivalName]);
            } else {
                $text .= ' ' . __('transfers.chat_agent_rival_pressure', [
                    'rival' => $rivalName,
                    'wage' => Money::format($negotiation->rival_offer_wage),
                ]);
            }
        }

        return $text;
    }

    /**
     * Resume text for a continued negotiation.
     */
    private function resumeText(GamePlayer $player, RenewalNegotiation $negotiation): string
    {
        $text = __('transfers.chat_counter_resume', [
            'player' => $player->name,
            'wage' => Money::format($negotiation->counter_offer),
            'years' => $negotiation->preferred_years,
        ]);

        if ($negotiation->hasActiveRivalOffer()) {
            $negotiation->loadMissing('rivalTeam');
            $text .= ' ' . __('transfers.chat_agent_rival_pressure', [
                'rival' => $negotiation->rivalTeam?->name ?? '',
                'wage' => Money::format($negotiation->rival_offer_wage),
            ]);
        }

        return $text;
    }

    /**
     * Structured rival-offer data for the chat UI (renders a highlight card).
     */
    private function rivalOfferContent(RenewalNegotiation $negotiation): array
    {
        if (!$negotiation->hasActiveRivalOffer()) {
            return [];
        }

        $negotiation->loadMissing('rivalTeam');

        return [
            'rivalOffer' => [
                'club' => $negotiation->rivalTeam?->name ?? '',
                'wage' => Money::format($negotiation->rival_offer_wage),
                'wageEuros' => (int) ($negotiation->rival_offer_wage / 100),
                'years' => $negotiation->rival_offer_years,
                'title' => __('transfers.chat_rival_offer_title'),
                'detail' => Money::format($negotiation->rival_offer_wage)
                    . '/año · ' . $negotiation->rival_offer_years
                    . ' ' . __('transfers.year_plural'),
            ],
            'patience' => (int) ($negotiation->agent_patience ?? 100),
            'patienceLevel' => $negotiation->patienceLevel(),
        ];
    }

    private function handleAcceptCounter(Game $game, GamePlayer $player): JsonResponse
    {
        $negotiation = RenewalNegotiation::where('game_player_id', $player->id)
            ->where('status', RenewalNegotiation::STATUS_PLAYER_COUNTERED)
            ->first();

        if (!$negotiation) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.renewal_failed'),
            ], 422);
        }

        // Salary cap: re-check against the wage the player is holding out for.
        $freedWage = $this->salaryCapService->effectiveWageFor($player);
        if (! $this->salaryCapService->canCommitWage($game, (int) $negotiation->counter_offer, $freedWage)) {
            return response()->json([
                'status' => 'error',
                'message' => $this->salaryCapService->blockMessage($game, $player->name, (int) $negotiation->counter_offer, $freedWage),
            ], 422);
        }

        $success = $this->contractService->acceptCounterOffer($negotiation);

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.renewal_failed'),
            ], 422);
        }

        $negotiation->refresh();

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'accepted',
            'round' => $negotiation->round,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('accepted', [
                    'text' => __('transfers.chat_agent_accepted', [
                        'player' => $player->name,
                        'wage' => Money::format($negotiation->counter_offer),
                        'years' => $negotiation->contract_years,
                    ]),
                    'wage' => (int) ($negotiation->counter_offer / 100),
                    'years' => $negotiation->contract_years,
                ]),
            ],
        ]);
    }

    private function agentMessage(string $type, array $content, ?array $options = null): array
    {
        return [
            'sender' => 'agent',
            'type' => $type,
            'content' => $content,
            'options' => $options,
        ];
    }

    /**
     * Calculate midpoint between two wages, in euros, rounded to nearest 10K.
     */
    private function calculateMidpointInEuros(int $wageCentsA, int $wageCentsB): int
    {
        return (int) (ceil(($wageCentsA + $wageCentsB) / 2 / 100 / 10000) * 10000);
    }
}
