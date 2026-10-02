<?php

namespace App\Modules\Commercial\Services;

use App\Models\Game;
use App\Models\GameSponsorDeal;

/**
 * Mints shirt / ad-board sponsor offers: fresh sponsor bids (createOffer)
 * and incumbent renewals (createRenewalOffer).
 *
 * Each fresh offer is priced independently — an annual base drawn within
 * the slot+tier band, scaled by a term multiplier so a longer deal pays
 * less per season. Offers are plausible standalone sponsor bids rather than
 * points engineered onto one shared tradeoff curve.
 */
class SponsorOfferFactory
{
    /**
     * Mint one pending sponsor offer for a slot: a brand eligible for the
     * sponsor tier that isn't already on the board, an annual fee drawn
     * within the slot+tier band, and a term within the configured bounds.
     * Returns null when every eligible brand is already pending.
     */
    public function createOffer(Game $game, string $slot, string $tier): ?GameSponsorDeal
    {
        $season = (int) $game->season;

        $sponsor = $this->pickAvailableSponsor($game, $season, $slot, $tier);
        if ($sponsor === null) {
            return null;
        }

        [$min, $max] = $this->tierBand($slot, $tier);
        $term = $this->pickTerm();

        return GameSponsorDeal::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'slot' => $slot,
            'sponsor_name' => $sponsor['name'],
            'tier' => $tier,
            'annual_value_cents' => $this->annualForTerm(random_int($min, $max), $term),
            'contract_seasons' => $term,
            'is_renewal' => false,
            'status' => GameSponsorDeal::STATUS_PENDING,
            'offered_season' => $season,
            'offered_at' => $game->current_date?->toDateTimeString(),
        ]);
    }

    /**
     * Mint a free renewal offer for the incumbent sponsor when a deal
     * expires: same brand, a fresh fee re-priced from the club's current
     * sponsor tier, and a one-season term so the decision returns each
     * pre-season. No friction — keeping a sponsor you already have costs
     * nothing.
     */
    public function createRenewalOffer(Game $game, GameSponsorDeal $expired, string $tier): GameSponsorDeal
    {
        $season = (int) $game->season;

        [$minValue, $maxValue] = $this->tierBand($expired->slot, $tier);
        $seasons = (int) config('commercial.sponsor_deals.renewal_seasons', 1);

        return GameSponsorDeal::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'slot' => $expired->slot,
            'sponsor_name' => $expired->sponsor_name,
            'tier' => $tier,
            'annual_value_cents' => random_int((int) $minValue, (int) $maxValue),
            'contract_seasons' => $seasons,
            'is_renewal' => true,
            'status' => GameSponsorDeal::STATUS_PENDING,
            'offered_season' => $season,
            'offered_at' => $game->current_date?->toDateTimeString(),
        ]);
    }

    /**
     * The [min, max] annual-value band for a slot+tier (cents), falling back
     * to the slot's local band for an unmapped tier so callers never read an
     * empty range.
     *
     * @return array{0: int, 1: int}
     */
    private function tierBand(string $slot, string $tier): array
    {
        $range = config("commercial.sponsor_deals.annual_value.{$slot}.{$tier}")
            ?? config("commercial.sponsor_deals.annual_value.{$slot}.local", [500_00, 3_000_00]);

        return [(int) $range[0], (int) $range[1]];
    }

    /**
     * The annual-fee multiplier for a contract length: 1.0 at one season
     * (the band is the headline rate), discounting toward longer terms.
     */
    private function termMultiplier(int $seasons): float
    {
        $curve = (array) config('commercial.sponsor_deals.term_value_multiplier', []);

        return (float) ($curve[$seasons] ?? 1.0);
    }

    private function annualForTerm(int $base, int $seasons): int
    {
        return (int) round($base * $this->termMultiplier($seasons));
    }

    private function pickTerm(): int
    {
        $min = (int) config('commercial.sponsor_deals.min_contract_seasons', 1);
        $max = (int) config('commercial.sponsor_deals.max_contract_seasons', 3);

        return random_int($min, $max);
    }

    /**
     * The brand pool: the naming-rights sponsors (global/national/regional
     * reach) plus the local-tier pool (universities, neighbourhood shops).
     * Two gates narrow it:
     *   1. `reach` must bid for the sponsor tier (a global giant won't chase
     *      a bottom-tier shirt; the corner bakery won't bid for a leader's
     *      chest).
     *   2. a non-global brand only operates in its home market, so it can
     *      only sponsor a club in its own country; global brands sponsor
     *      anywhere.
     * Null when no eligible brand is left.
     *
     * @return array{name: string, reach: string, country?: string}|null
     */
    private function pickAvailableSponsor(Game $game, int $season, string $slot, string $tier): ?array
    {
        $pool = array_merge(
            (array) config('commercial.naming_rights.sponsors', []),
            $this->localSponsors(),
        );
        if (empty($pool)) {
            return null;
        }

        $eligibleReaches = (array) config("commercial.sponsor_deals.tier_reach.{$tier}", []);
        $country = $game->country;
        $pool = array_filter($pool, function (array $sponsor) use ($eligibleReaches, $country) {
            $reach = $sponsor['reach'] ?? null;

            if (! empty($eligibleReaches) && ! in_array($reach, $eligibleReaches, true)) {
                return false;
            }

            // Global brands are country-agnostic; everyone else is home-market only.
            return $reach === 'global' || ($sponsor['country'] ?? null) === $country;
        });

        // A brand can't bid twice for the same slot in the same season.
        $taken = GameSponsorDeal::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('slot', $slot)
            ->where('status', GameSponsorDeal::STATUS_PENDING)
            ->where('offered_season', $season)
            ->pluck('sponsor_name')
            ->all();

        $available = array_values(array_filter(
            $pool,
            fn (array $sponsor) => ! in_array($sponsor['name'], $taken, true),
        ));

        if (empty($available)) {
            return null;
        }

        return $available[random_int(0, count($available) - 1)];
    }

    /**
     * @return array<int, array{name: string, reach: string, country: string}>
     */
    private function localSponsors(): array
    {
        $byCountry = (array) config('commercial.sponsor_deals.local_sponsors', []);
        $flat = [];
        foreach ($byCountry as $sponsors) {
            foreach ((array) $sponsors as $sponsor) {
                $flat[] = $sponsor;
            }
        }

        return $flat;
    }
}
