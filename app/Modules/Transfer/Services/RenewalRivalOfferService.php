<?php

namespace App\Modules\Transfer\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Models\Team;

/**
 * Generates credible rival-club offers that pressure renewal negotiations.
 *
 * When a rival offer is active, the player's agent uses it as leverage:
 * the user must match or beat the rival wage to keep the player at the table.
 */
class RenewalRivalOfferService
{
    /**
     * Maybe attach a rival offer to a fresh negotiation.
     * Higher chance for star players and players with little contract left.
     */
    public function maybeAttachRivalOffer(Game $game, GamePlayer $player, RenewalNegotiation $negotiation): void
    {
        if ($negotiation->hasActiveRivalOffer()) {
            return;
        }

        $chance = 0.22;

        // Stars attract suitors
        if (($player->overall_score ?? 0) >= 82) {
            $chance += 0.18;
        } elseif (($player->overall_score ?? 0) >= 75) {
            $chance += 0.08;
        }

        // A player in her last contract year is a Bosman risk: more vultures
        if ($this->contractMonthsLeft($game, $player) <= 12) {
            $chance += 0.15;
        }

        if (mt_rand(1, 100) > (int) ($chance * 100)) {
            return;
        }

        $this->attachRivalOffer($game, $player, $negotiation);
    }

    /**
     * A rival may also show up mid-negotiation if the user lowballs repeatedly.
     */
    public function maybeEscalateRivalOffer(Game $game, GamePlayer $player, RenewalNegotiation $negotiation): void
    {
        if ($negotiation->hasActiveRivalOffer()) {
            return;
        }

        // Only from round 2 onwards, and more likely when patience is low
        if ($negotiation->round < 2) {
            return;
        }

        $chance = 0.25 + (100 - (int) ($negotiation->agent_patience ?? 100)) / 100 * 0.35;

        if (mt_rand(1, 100) > (int) ($chance * 100)) {
            return;
        }

        $this->attachRivalOffer($game, $player, $negotiation);
    }

    /**
     * Pick a credible rival club and store its offer on the negotiation.
     */
    public function attachRivalOffer(Game $game, GamePlayer $player, RenewalNegotiation $negotiation): void
    {
        $rival = $this->pickRivalClub($game);

        if (!$rival) {
            return;
        }

        $demand = (int) ($negotiation->player_demand ?? $player->annual_wage);

        // The rival offers slightly above the player's demand: real pressure
        $wage = (int) ($demand * (1.05 + mt_rand(0, 20) / 100));
        $wage = $this->roundWage($wage);
        $years = min(5, max(2, (int) ($negotiation->preferred_years ?? 3)));

        $negotiation->update([
            'rival_team_id' => $rival->id,
            'rival_offer_wage' => $wage,
            'rival_offer_years' => $years,
            'rival_offer_active' => true,
        ]);

        $negotiation->refresh();
    }

    /**
     * A credible rival: a real club from the same country (same league system),
     * not the user's club and not a reserve side.
     */
    public function pickRivalClub(Game $game): ?Team
    {
        return Team::query()
            ->where('country', $game->country)
            ->where('type', 'club')
            ->where('is_placeholder', false)
            ->whereNull('parent_team_id')
            ->where('id', '!=', $game->team_id)
            ->inRandomOrder()
            ->first();
    }

    /**
     * Months left on the player's current contract (best effort).
     */
    private function contractMonthsLeft(Game $game, GamePlayer $player): int
    {
        try {
            $end = $player->contract_end ?? $player->contract_until ?? null;
            if (!$end) {
                return 24;
            }
            $endDate = $end instanceof \DateTimeInterface ? $end : new \DateTime((string) $end);
            $now = $game->current_date instanceof \DateTimeInterface
                ? $game->current_date
                : new \DateTime((string) $game->current_date);
            $diff = $now->diff($endDate);
            return max(0, $diff->y * 12 + $diff->m);
        } catch (\Throwable) {
            return 24;
        }
    }

    private function roundWage(int $wageCents): int
    {
        $unit = $wageCents >= 1_000_000_00 ? 100_000_00 : 10_000_00;

        return (int) (round($wageCents / $unit) * $unit);
    }
}
