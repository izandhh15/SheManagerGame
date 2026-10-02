<?php

namespace App\Modules\Commercial\Services;

use App\Models\Game;
use App\Models\GameSponsorDeal;

/**
 * Read-side for the sponsor surfaces on the Commercial page: the active
 * shirt / ad-board deals (if any) and each slot's pending offer board.
 * Pure reads — every mutation lives on SponsorService.
 */
class SponsorReadService
{
    public function __construct(
        private readonly SponsorService $sponsorService,
    ) {}

    /**
     * Commercial read-side for the Commercial page, one entry per slot.
     *
     * @return array{slots: array<string, array<string, mixed>>}
     */
    public function buildSponsorPanel(Game $game): array
    {
        $season = (int) $game->season;
        $slots = [];

        foreach (GameSponsorDeal::SLOTS as $slot) {
            $active = GameSponsorDeal::activeForGameSlot($game->id, $game->team_id, $slot);

            $activeDeal = null;
            if ($active !== null) {
                $activeDeal = [
                    'sponsor_name' => $active->sponsor_name,
                    'tier' => $active->tier,
                    'annual_value_cents' => $active->annual_value_cents,
                    'end_season' => $active->end_season,
                    'seasons_remaining' => max(0, ($active->end_season ?? $season) - $season + 1),
                ];
            }

            $offers = $active === null
                ? GameSponsorDeal::pendingForGameSlot($game->id, $game->team_id, $slot, $season)
                    ->map(fn (GameSponsorDeal $deal) => [
                        'id' => $deal->id,
                        'sponsor_name' => $deal->sponsor_name,
                        'tier' => $deal->tier,
                        'annual_value_cents' => $deal->annual_value_cents,
                        'contract_seasons' => $deal->contract_seasons,
                        'is_renewal' => $deal->is_renewal,
                    ])
                    ->all()
                : [];

            $slots[$slot] = [
                'activeDeal' => $activeDeal,
                'offers' => $offers,
            ];
        }

        return ['slots' => $slots];
    }
}
