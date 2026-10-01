<?php

declare(strict_types=1);

namespace App\Modules\Stadium\Services;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\Team;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Services\TournamentCreationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Venue organization for national-team COMPETITIVE matches without a fixed
 * venue: UEFA Women's Nations League (group stage through semifinals) and
 * the World Cup / Euro qualifiers.
 *
 * The manager must request a stadium and can offer the club ANY amount
 * within the federation budget. The club receives the money:
 *  - Women's club ground: the owning club gets the fee (credited to the
 *    user's club game in dual mode via FinancialTransaction).
 *  - Men's big stadium: the men's club gets the fee, and may return a
 *    "contraprestación" (~20% for big offers) earmarked for the youth
 *    academy (credited to the dual-partner club, or back to the federation
 *    budget in solo national mode).
 *
 * A bigger offer increases the acceptance chance. National stadiums and
 * the neutral fallback are free. If nobody accepts (or the user never
 * organizes), the match is played at the neutral fallback ground.
 */
class NationalVenueOrganizationService
{
    /** Minimum men's-stadium offer (€) that can trigger a quid-pro-quo rebate. */
    public const REBATE_MIN_OFFER = 1_000_000;

    /** Share of a big men's-stadium fee returned for the academy. */
    public const REBATE_SHARE = 0.20;

    public function __construct(
        private readonly NationalVenueRequestService $venueService,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Competition ids whose home matches need venue organization.
     *
     * @return list<string>
     */
    public function venueCompetitions(): array
    {
        return array_values(array_unique(array_merge(
            [TournamentCreationService::WNL_ID, TournamentCreationService::WEUROQ_ID],
            TournamentCreationService::WQC_IDS,
        )));
    }

    /**
     * Upcoming home matches of the national team that still need a venue:
     * not played, no venue set, not waiting on the user's club, not a final
     * (finals are played at a fixed neutral venue).
     *
     * @return Collection<int, GameMatch>
     */
    public function pendingMatches(Game $game): Collection
    {
        if (! $game->isTournamentMode()) {
            return collect();
        }

        return GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->whereIn('competition_id', $this->venueCompetitions())
            ->whereNull('neutral_venue_name')
            ->where('venue_status', '!=', 'pending_club')
            ->whereDate('scheduled_date', '>=', $game->current_date ?? Carbon::today()->toDateString())
            ->orderBy('scheduled_date')
            ->with(['awayTeam', 'competition'])
            ->get()
            ->reject(fn (GameMatch $m) => $this->isFinal($m));
    }

    /**
     * Matches waiting for the user's own club decision (dual mode).
     *
     * @return Collection<int, GameMatch>
     */
    public function pendingClubDecisions(Game $game): Collection
    {
        if (! $game->isTournamentMode()) {
            return collect();
        }

        return GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->where('venue_status', 'pending_club')
            ->orderBy('scheduled_date')
            ->with(['awayTeam'])
            ->get();
    }

    private function isFinal(GameMatch $match): bool
    {
        $name = (string) ($match->round_name ?? '');
        if ($name === '') {
            return false;
        }

        return (bool) preg_match('/\bfinal\b/i', $name)
            && ! (bool) preg_match('/semi/i', $name);
    }

    /**
     * Organize the venue for a competitive national-team match.
     *
     * $venueType: national|club|mens|neutral
     * $selection: ['stadium' => name] for national, ['club_team_id' => uuid]
     *            for club, ['mens_stadium' => name] for mens.
     *
     * @return array{ok: bool, message: string, accepted: bool, rebate: int}
     */
    public function organize(
        Game $game,
        GameMatch $match,
        string $venueType,
        array $selection,
        int $offerEuros,
    ): array {
        if (! $game->isTournamentMode()
            || $match->game_id !== $game->id
            || $match->home_team_id !== $game->team_id
            || $match->played
            || $match->neutral_venue_name !== null
            || $match->venue_status === 'pending_club'
        ) {
            return $this->fail('game.venue_org_invalid_match');
        }

        if (! in_array($match->competition_id, $this->venueCompetitions(), true)
            || $this->isFinal($match)
        ) {
            return $this->fail('game.venue_org_invalid_match');
        }

        $budget = (int) ($game->federation_budget ?? 0);
        $offerEuros = max(0, $offerEuros);
        if ($offerEuros > $budget) {
            return $this->fail('game.venue_org_over_budget');
        }

        $userTeam = Team::find($game->team_id);
        $opponent = Team::find($match->away_team_id);

        return match ($venueType) {
            'national' => $this->organizeNational($game, $match, $selection),
            'neutral' => $this->organizeNeutral($game, $match),
            'club' => $this->organizeClub($game, $match, $selection, $offerEuros, $userTeam, $opponent, $budget),
            'mens' => $this->organizeMens($game, $match, $selection, $userTeam, $opponent, $budget),
            default => $this->fail('game.venue_org_invalid_match'),
        };
    }

    private function fail(string $key): array
    {
        return ['ok' => false, 'message' => __($key), 'accepted' => false, 'rebate' => 0];
    }

    private function organizeNational(Game $game, GameMatch $match, array $selection): array
    {
        $stadiums = $this->venueService->nationalStadiums();
        $stadium = $stadiums->firstWhere('stadium', $selection['stadium'] ?? null);
        if (! $stadium) {
            return $this->fail('game.friendly_invalid_stadium');
        }

        $match->update([
            'neutral_venue_name' => $stadium['stadium'],
            'neutral_venue_capacity' => $stadium['capacity'],
            'venue_status' => 'confirmed',
            'venue_fee' => 0,
        ]);

        return [
            'ok' => true,
            'message' => __('game.venue_org_confirmed', ['stadium' => $stadium['stadium']]),
            'accepted' => true,
            'rebate' => 0,
        ];
    }

    private function organizeNeutral(Game $game, GameMatch $match): array
    {
        $match->update([
            'neutral_venue_name' => NationalVenueRequestService::NEUTRAL_VENUE_NAME,
            'neutral_venue_capacity' => NationalVenueRequestService::NEUTRAL_VENUE_CAPACITY,
            'venue_status' => 'confirmed',
            'venue_fee' => 0,
        ]);

        return [
            'ok' => true,
            'message' => __('game.venue_org_neutral'),
            'accepted' => true,
            'rebate' => 0,
        ];
    }

    private function organizeClub(
        Game $game,
        GameMatch $match,
        array $selection,
        int $offerEuros,
        ?Team $userTeam,
        ?Team $opponent,
        int $budget,
    ): array {
        $clubTeam = Team::where('type', 'club')
            ->where('is_placeholder', false)
            ->find($selection['club_team_id'] ?? null);

        if (! $clubTeam || ! $clubTeam->stadium_name) {
            return $this->fail('game.friendly_invalid_stadium');
        }

        // The user manages this club (dual mode): they decide. The offer is
        // stored on the match and only charged if they accept.
        if ($this->venueService->userManagesClub($game, $clubTeam->id)) {
            $match->update([
                'venue_status' => 'pending_club',
                'venue_request_team_id' => $clubTeam->id,
                'venue_request_type' => 'club',
                'venue_fee' => $offerEuros,
            ]);

            $partner = $game->dualPartner();
            if ($partner) {
                $this->notifications->notifyStadiumRequest(
                    $partner,
                    $userTeam?->name ?? '',
                    $opponent?->name ?? '',
                    Carbon::parse($match->scheduled_date)->format('d/m/Y'),
                    $clubTeam->stadium_name,
                    $match->id,
                    $game->id,
                );
            }

            return [
                'ok' => true,
                'message' => __('game.venue_org_requested', [
                    'stadium' => $clubTeam->stadium_name,
                    'club' => $clubTeam->name,
                ]),
                'accepted' => false,
                'rebate' => 0,
            ];
        }

        // AI club decides now; a bigger offer helps.
        $decision = $this->venueService->evaluateClubRequest($clubTeam, $userTeam, $opponent, $offerEuros);

        if (! $decision['accepted']) {
            return [
                'ok' => false,
                'message' => __('game.venue_org_rejected', [
                    'stadium' => $clubTeam->stadium_name,
                    'excuse' => __($decision['excuse']),
                ]),
                'accepted' => false,
                'rebate' => 0,
            ];
        }

        DB::transaction(function () use ($game, $match, $clubTeam, $offerEuros, $budget) {
            $game->update(['federation_budget' => $budget - $offerEuros]);
            $match->update([
                'neutral_venue_name' => $clubTeam->stadium_name,
                'neutral_venue_capacity' => (int) $clubTeam->stadium_seats,
                'venue_status' => 'confirmed',
                'venue_request_team_id' => $clubTeam->id,
                'venue_request_type' => 'club',
                'venue_fee' => $offerEuros,
            ]);
            $this->payClub($game, $clubTeam, $offerEuros, $match);
        });

        $this->notifications->notifyStadiumRequestResult(
            $game, true, $clubTeam->stadium_name ?? '', null,
        );

        return [
            'ok' => true,
            'message' => __('game.venue_org_accepted_fee', [
                'stadium' => $clubTeam->stadium_name,
                'fee' => number_format($offerEuros, 0, ',', '.'),
            ]),
            'accepted' => true,
            'rebate' => 0,
        ];
    }

    /**
     * The men's club names the price (rental_price in the catalogue): the
     * federation pays it or walks away. Each club sets its own fee —
     * Mestalla costs more than the Ciutat de València; municipal grounds
     * like La Cartuja belong to the city council and anyone can rent them.
     * A national team playing at its OWN ground (national_team: true in
     * data/stadiums.json) never pays: that is the free 'national' option.
     */
    private function organizeMens(
        Game $game,
        GameMatch $match,
        array $selection,
        ?Team $userTeam,
        ?Team $opponent,
        int $budget,
    ): array {
        $mens = collect($this->venueService->mensStadiums())
            ->firstWhere('key', $selection['mens_stadium'] ?? null);

        if (! $mens) {
            return $this->fail('game.friendly_invalid_stadium');
        }

        $price = (int) ($mens['rental_price'] ?? 0);
        $owner = $mens['owner'] ?? $mens['club'] ?? $mens['stadium'];

        if ($price > $budget) {
            return [
                'ok' => false,
                'message' => __('game.venue_org_mens_cant_afford', [
                    'stadium' => $mens['stadium'],
                    'price' => number_format($price, 0, ',', '.'),
                    'owner' => $owner,
                ]),
                'accepted' => false,
                'rebate' => 0,
            ];
        }

        // The men's club (always AI) decides; the price is theirs, so the
        // offer no longer influences the decision.
        $decision = $this->venueService->evaluateMensRequest($mens['club'], $userTeam, $opponent);

        if (! $decision['accepted']) {
            return [
                'ok' => false,
                'message' => __('game.venue_org_rejected', [
                    'stadium' => $mens['stadium'],
                    'excuse' => __($decision['excuse']),
                ]),
                'accepted' => false,
                'rebate' => 0,
            ];
        }

        $rebate = 0;
        if ($price >= self::REBATE_MIN_OFFER) {
            // Quid pro quo: the men's club returns ~20% for the academy.
            $rebate = (int) floor($price * self::REBATE_SHARE / 100000) * 100000;
        }

        DB::transaction(function () use ($game, $match, $mens, $price, $rebate, $budget) {
            $game->update(['federation_budget' => $budget - $price]);
            $match->update([
                'neutral_venue_name' => $mens['stadium'],
                'neutral_venue_capacity' => $mens['capacity'],
                'venue_status' => 'confirmed',
                'venue_request_team_id' => null,
                'venue_request_type' => 'mens',
                'venue_fee' => $price,
            ]);
            if ($rebate > 0) {
                $this->grantRebate($game, $mens['club'] ?? $mens['owner'], $mens['stadium'], $rebate);
            }
        });

        $this->notifications->notifyStadiumRequestResult(
            $game, true, $mens['stadium'], null,
        );

        $message = __('game.venue_org_accepted_fee', [
            'stadium' => $mens['stadium'],
            'fee' => number_format($price, 0, ',', '.'),
        ]);
        if ($rebate > 0) {
            $message .= ' ' . __('game.venue_org_rebate', [
                'club' => $mens['club'] ?? $mens['owner'],
                'amount' => number_format($rebate, 0, ',', '.'),
            ]);
        }

        return ['ok' => true, 'message' => $message, 'accepted' => true, 'rebate' => $rebate];
    }

    /**
     * The club receives the venue fee. In dual mode the money lands in the
     * user's club game (FinancialTransaction income); AI clubs simply keep
     * it (recorded on the match).
     */
    private function payClub(Game $nationalGame, Team $clubTeam, int $feeEuros, GameMatch $match): void
    {
        if ($feeEuros <= 0) {
            return;
        }

        $partner = $nationalGame->dualPartner();
        if ($partner === null || $partner->team_id !== $clubTeam->id) {
            return;
        }

        FinancialTransaction::create([
            'game_id' => $partner->id,
            'type' => FinancialTransaction::TYPE_INCOME,
            'category' => 'venue_fee',
            'amount' => $feeEuros * 100,
            'description' => __('game.venue_fee_income_desc', [
                'team' => $nationalGame->team?->name ?? '',
                'stadium' => $clubTeam->stadium_name ?? '',
            ]),
            'transaction_date' => Carbon::parse($match->scheduled_date)->toDateString(),
        ]);
    }

    /**
     * Quid pro quo from the men's club: ~20% of a big venue fee comes back
     * earmarked for the youth academy. In dual mode it lands in the club
     * game; in solo national mode it tops the federation budget back up.
     */
    private function grantRebate(Game $nationalGame, string $mensClub, string $stadium, int $rebateEuros): void
    {
        $partner = $nationalGame->dualPartner();

        if ($partner !== null && ! $partner->isTournamentMode()) {
            FinancialTransaction::create([
                'game_id' => $partner->id,
                'type' => FinancialTransaction::TYPE_INCOME,
                'category' => 'venue_fee',
                'amount' => $rebateEuros * 100,
                'description' => __('game.venue_rebate_income_desc', [
                    'club' => $mensClub,
                    'stadium' => $stadium,
                ]),
                'transaction_date' => Carbon::now()->toDateString(),
            ]);

            $this->notifications->create(
                game: $partner,
                type: GameNotification::TYPE_STADIUM_REQUEST_RESULT,
                title: __('game.venue_rebate_title'),
                message: __('game.venue_rebate_club_desc', [
                    'club' => $mensClub,
                    'amount' => number_format($rebateEuros, 0, ',', '.'),
                ]),
            );

            return;
        }

        $nationalGame->update([
            'federation_budget' => (int) $nationalGame->federation_budget + $rebateEuros,
        ]);
    }
}
