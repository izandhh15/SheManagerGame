<?php

namespace App\Modules\Commercial\Services;

use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GameSponsorDeal;
use App\Models\GameStanding;
use App\Models\TeamReputation;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Orchestrates the two manager-chosen sponsor slots: shirt sponsor
 * (camiseta) and ad-board sponsor (valla publicitaria). Stadium naming
 * rights keep living in NamingRightsService.
 *
 * Unlike naming rights (sought proactively), sponsor offers ARRIVE on their
 * own: a monthly top-up mints fresh bids whose stature tracks how the team
 * is doing — division + league position. Bottom of the second tier gets
 * the local university or the neighbourhood shop; top of the first tier
 * gets the big brands chasing them.
 *
 * A deal pays a fixed recurring annual fee while active. Accepting one
 * rejects the competing offers for that slot. There is no signing window:
 * shirt and board sponsors can be signed mid-season, like in real life.
 */
class SponsorService
{
    public function __construct(
        private readonly SponsorOfferFactory $offerFactory,
        private readonly NotificationService $notificationService,
    ) {}

    // ── Sponsor stature ─────────────────────────────────────────────────

    /**
     * The sponsor tier a club can attract right now: local < regional <
     * nacional < internacional. Resolved from division + live league
     * position; before the table exists (pre-season) it falls back to the
     * club's reputation tier.
     */
    public function resolveSponsorTier(Game $game): string
    {
        $competition = $game->competition;
        $division = $competition?->tier !== null ? (int) $competition->tier : null;

        $standing = null;
        $teamCount = 0;
        if ($competition !== null) {
            $standings = GameStanding::query()
                ->where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->orderBy('position')
                ->get();
            $teamCount = $standings->count();
            $standing = $standings->firstWhere('team_id', $game->team_id);
        }

        if ($standing !== null && $division !== null && $teamCount > 0) {
            return $this->tierFromDivisionAndPosition($division, (int) $standing->position, $teamCount);
        }

        // No table yet (pre-season): stature tracks reputation instead.
        return $this->tierFromReputation(TeamReputation::resolveLevel($game->id, $game->team_id));
    }

    /**
     * Division + position → sponsor tier. The top flight always attracts at
     * least national brands; the second tier spans the whole range (top →
     * nacional, bottom → local, e.g. the Primera RFEF basement gets the
     * local university); the third tier and below top out at regional.
     */
    private function tierFromDivisionAndPosition(int $division, int $position, int $teamCount): string
    {
        if ($division <= 1) {
            return $position <= 4
                ? GameSponsorDeal::TIER_INTERNACIONAL
                : GameSponsorDeal::TIER_NACIONAL;
        }

        if ($division === 2) {
            if ($position <= 3) {
                return GameSponsorDeal::TIER_NACIONAL;
            }

            return $position > $teamCount - 3
                ? GameSponsorDeal::TIER_LOCAL
                : GameSponsorDeal::TIER_REGIONAL;
        }

        return $position <= 4
            ? GameSponsorDeal::TIER_REGIONAL
            : GameSponsorDeal::TIER_LOCAL;
    }

    private function tierFromReputation(string $level): string
    {
        return match ($level) {
            'elite' => GameSponsorDeal::TIER_INTERNACIONAL,
            'continental', 'established' => GameSponsorDeal::TIER_NACIONAL,
            'modest' => GameSponsorDeal::TIER_REGIONAL,
            default => GameSponsorDeal::TIER_LOCAL,
        };
    }

    // ── Monthly arrival (offers come to the manager) ────────────────────

    /**
     * Top each slot's offer board up to the cap with fresh bids at the
     * club's current sponsor tier. Idempotent per game-calendar month: once
     * offers have arrived this month, a second call is a no-op — so
     * rejecting everything doesn't summon an endless parade of sponsors.
     * Notifies the manager when fresh offers land.
     *
     * @return array<int, GameSponsorDeal> the offers minted this month
     */
    public function generateMonthlyOffers(Game $game): array
    {
        if ($game->isTournamentMode()) {
            return [];
        }

        if ($this->alreadyGeneratedThisMonth($game)) {
            return [];
        }

        $tier = $this->resolveSponsorTier($game);
        $minted = [];

        foreach (GameSponsorDeal::SLOTS as $slot) {
            if (GameSponsorDeal::activeForGameSlot($game->id, $game->team_id, $slot) !== null) {
                continue;
            }

            $minted = array_merge($minted, $this->topUpSlot($game, $slot, $tier));
        }

        if (! empty($minted)) {
            $count = count($minted);
            $this->notificationService->create(
                game: $game,
                type: GameNotification::TYPE_COMMERCIAL,
                title: __('notifications.sponsor_offers_arrived_title'),
                message: trans_choice('notifications.sponsor_offers_arrived_message', $count, ['count' => $count]),
                priority: GameNotification::PRIORITY_INFO,
            );
        }

        return $minted;
    }

    /**
     * Season-start seeding (runs from the season processor): roll last
     * season's deals over, then put the first board of offers on the table
     * for each slot without an active deal.
     */
    public function seedSeasonBoard(Game $game): void
    {
        if ($game->isTournamentMode()) {
            return;
        }

        $this->rolloverForNewSeason($game);

        $tier = $this->resolveSponsorTier($game);

        foreach (GameSponsorDeal::SLOTS as $slot) {
            if (GameSponsorDeal::activeForGameSlot($game->id, $game->team_id, $slot) !== null) {
                continue;
            }

            $this->topUpSlot($game, $slot, $tier);
        }
    }

    /**
     * Mint offers for a slot until the board reaches the pending cap (or
     * the eligible brand pool runs dry).
     *
     * @return array<int, GameSponsorDeal>
     */
    private function topUpSlot(Game $game, string $slot, string $tier): array
    {
        $season = (int) $game->season;
        $cap = (int) config('commercial.sponsor_deals.max_pending_offers', 3);

        $pending = GameSponsorDeal::pendingForGameSlot($game->id, $game->team_id, $slot, $season)->count();

        $minted = [];
        for ($i = $pending; $i < $cap; $i++) {
            $offer = $this->offerFactory->createOffer($game, $slot, $tier);
            if ($offer === null) {
                break;
            }
            $minted[] = $offer;
        }

        return $minted;
    }

    private function alreadyGeneratedThisMonth(Game $game): bool
    {
        $monthStart = $game->current_date?->copy()->startOfMonth()->toDateTimeString()
            ?? now()->startOfMonth()->toDateTimeString();

        return GameSponsorDeal::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('offered_at', '>=', $monthStart)
            ->exists();
    }

    // ── Season rollover (pre-season processor) ───────────────────────────

    /**
     * Roll sponsor deals over into a new season: expire any deal that has
     * run its term (offering the incumbent a free renewal) and clear
     * unaccepted offers left over from previous seasons. Idempotent.
     */
    public function rolloverForNewSeason(Game $game): void
    {
        $season = (int) $game->season;
        $tier = $this->resolveSponsorTier($game);

        foreach (GameSponsorDeal::SLOTS as $slot) {
            $active = GameSponsorDeal::activeForGameSlot($game->id, $game->team_id, $slot);
            if ($active && $active->end_season !== null && $active->end_season < $season) {
                $active->update(['status' => GameSponsorDeal::STATUS_EXPIRED]);
                $this->offerFactory->createRenewalOffer($game, $active, $tier);
            }
        }

        GameSponsorDeal::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('status', GameSponsorDeal::STATUS_PENDING)
            ->where('offered_season', '<', $season)
            ->update(['status' => GameSponsorDeal::STATUS_EXPIRED]);
    }

    // ── Player actions ──────────────────────────────────────────────────

    /**
     * Accept a pending sponsor offer: activate it for its slot, reject the
     * competing offers for that slot, and fold the income into the season's
     * projected finances.
     */
    public function acceptOffer(Game $game, string $dealId): GameSponsorDeal
    {
        if ($game->isTournamentMode()) {
            throw new InvalidArgumentException('messages.sponsor_offer_unavailable');
        }

        $deal = GameSponsorDeal::query()
            ->where('id', $dealId)
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('status', GameSponsorDeal::STATUS_PENDING)
            ->first();

        if (! $deal) {
            throw new InvalidArgumentException('messages.sponsor_offer_unavailable');
        }

        if (GameSponsorDeal::activeForGameSlot($game->id, $game->team_id, $deal->slot) !== null) {
            throw new InvalidArgumentException('messages.sponsor_deal_active');
        }

        $season = (int) $game->season;

        // Activating the deal, rejecting its competitors and folding the
        // income into the projections must be atomic: a failure halfway
        // would leave an active deal with stale projections.
        DB::transaction(function () use ($game, $deal, $season) {
            $deal->update([
                'status' => GameSponsorDeal::STATUS_ACTIVE,
                'start_season' => $season,
                'end_season' => $season + $deal->contract_seasons - 1,
            ]);

            // Reject the competing offers the manager passed over.
            GameSponsorDeal::query()
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->where('slot', $deal->slot)
                ->where('status', GameSponsorDeal::STATUS_PENDING)
                ->where('id', '!=', $deal->id)
                ->update(['status' => GameSponsorDeal::STATUS_REJECTED]);

            $this->refreshProjectedSponsors($game);
        });

        return $deal;
    }

    /**
     * Turn a pending offer down: it leaves the board for good.
     */
    public function rejectOffer(Game $game, string $dealId): GameSponsorDeal
    {
        $deal = GameSponsorDeal::query()
            ->where('id', $dealId)
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('status', GameSponsorDeal::STATUS_PENDING)
            ->first();

        if (! $deal) {
            throw new InvalidArgumentException('messages.sponsor_offer_unavailable');
        }

        $deal->update(['status' => GameSponsorDeal::STATUS_REJECTED]);

        return $deal;
    }

    // ── Revenue (called by Finance projection / Season settlement) ────────

    /**
     * Projected sponsor income for a slot: the active deal's fixed annual
     * fee. Zero when no deal is active.
     */
    public function projectedRevenueForGame(Game $game, string $slot): int
    {
        $deal = GameSponsorDeal::activeForGameSlot($game->id, $game->team_id, $slot);

        return $deal ? (int) $deal->annual_value_cents : 0;
    }

    /**
     * Settled sponsor income: the active deal's fixed annual fee — the
     * sponsor pays the same regardless of results. Zero when no deal is
     * active. Mirrors projectedRevenueForGame so projection and settlement
     * agree exactly.
     */
    public function settledRevenueForGame(Game $game, string $slot): int
    {
        return $this->projectedRevenueForGame($game, $slot);
    }

    // ── Internals ─────────────────────────────────────────────────────────

    /**
     * Recompute the projected shirt/board lines on the current finances row
     * and fold the difference into projected totals/surplus. Keeps the
     * budget consistent whether a deal is signed before or after the
     * pre-season projection ran. No-op when there's no finances row yet.
     */
    private function refreshProjectedSponsors(Game $game): void
    {
        $finances = $game->currentFinances;
        if (! $finances) {
            return;
        }

        $shirt = $this->projectedRevenueForGame($game, GameSponsorDeal::SLOT_SHIRT);
        $board = $this->projectedRevenueForGame($game, GameSponsorDeal::SLOT_AD_BOARD);

        $delta = ($shirt - (int) $finances->projected_shirt_sponsor_revenue)
            + ($board - (int) $finances->projected_ad_board_revenue);

        if ($delta === 0) {
            return;
        }

        $finances->projected_shirt_sponsor_revenue = $shirt;
        $finances->projected_ad_board_revenue = $board;
        $finances->projected_total_revenue += $delta;
        $finances->projected_surplus += $delta;
        $finances->save();
    }
}
