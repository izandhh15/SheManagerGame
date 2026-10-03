<?php

declare(strict_types=1);

namespace App\Modules\Government\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\Team;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Government-paid friendlies (F4, 0.3.9): when a national team has NO
 * competition in progress (no Nations, no World Cup, no Olympics...),
 * governments (Qatar, Saudi Arabia...) occasionally offer to PAY for a
 * friendly. The offer arrives as in-game mail; the player ACCEPTS or
 * REJECTS it.
 *
 * Accepted friendlies may be staged abroad (F3 exception) and the
 * government fee lands in the federation budget.
 */
class GovernmentFriendlyService
{
    /**
     * Major national-team competitions: if the game has an unplayed match
     * in any of these, the team is "busy" and no offer is made.
     */
    public const BUSY_COMPETITIONS = [
        'WWCU27', 'WOLYMP', 'WEURO', 'WNATIONS',
        'WWCQ', 'WEUROQ', 'WQCAF', 'WQAFC', 'WQCONC', 'WQOFC',
    ];

    /**
     * Governments that bankroll friendlies. Real country names only.
     */
    public const GOVERNMENTS = [
        'Qatar',
        'Arabia Saudí',
        'Emiratos Árabes Unidos',
        'Marruecos',
    ];

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Does this national-team game have a tournament/qualifier in progress?
     */
    public function hasCompetitionInProgress(Game $game): bool
    {
        return GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->whereIn('competition_id', self::BUSY_COMPETITIONS)
            ->exists();
    }

    /**
     * Maybe send a government offer. Call after advancing a national-team
     * matchday. Returns the notification, or null if no offer was made.
     */
    public function maybeOffer(Game $game): ?GameNotification
    {
        if (($game->team?->type ?? null) !== 'national') {
            return null;
        }

        if ($this->hasCompetitionInProgress($game)) {
            return null;
        }

        // One pending offer at a time.
        $pending = GameNotification::where('game_id', $game->id)
            ->ofType(GameNotification::TYPE_GOVERNMENT_FRIENDLY_OFFER)
            ->whereNull('read_at')
            ->exists();
        if ($pending) {
            return null;
        }

        // Occasional: ~35% per check.
        if (mt_rand(1, 100) > 35) {
            return null;
        }

        $government = self::GOVERNMENTS[array_rand(self::GOVERNMENTS)];
        $opponent = $this->pickOpponent($game);
        if ($opponent === null) {
            return null;
        }

        // Fee scales with the team's level: 1M€ – 3M€.
        $amount = mt_rand(10, 30) * 100000;

        return $this->notifications->create(
            $game,
            GameNotification::TYPE_GOVERNMENT_FRIENDLY_OFFER,
            __('game.gov_friendly_offer_title', ['government' => $government]),
            __('game.gov_friendly_offer_body', [
                'government' => $government,
                'opponent' => $opponent->name,
                'amount' => number_format($amount, 0, ',', '.'),
            ]),
            GameNotification::PRIORITY_WARNING,
            [
                'government' => $government,
                'amount' => $amount,
                'opponent_team_id' => $opponent->id,
                'opponent_name' => $opponent->name,
                'status' => 'pending',
            ],
            'mail',
        );
    }

    /**
     * Accept the offer: schedule the friendly (abroad — F3 exception) and
     * credit the federation budget.
     *
     * The whole accept runs in a transaction with the offer row locked: a
     * double POST racing here serializes on the lock, so the second one
     * sees status != pending instead of crediting 1–3M€ twice. The
     * "no competition in progress" rule is re-checked at accept time — an
     * offer accepted weeks later must not land the friendly in the middle
     * of a World Cup / Euro.
     *
     * @return array{ok: bool, error: string|null}
     */
    public function accept(GameNotification $notification): array
    {
        return DB::transaction(function () use ($notification) {
            $locked = GameNotification::whereKey($notification->id)->lockForUpdate()->first();

            if ($locked === null) {
                return ['ok' => false, 'error' => 'game.gov_friendly_invalid'];
            }

            $meta = $locked->metadata ?? [];
            if (($meta['status'] ?? null) !== 'pending') {
                return ['ok' => false, 'error' => 'game.gov_friendly_already_answered'];
            }

            $game = $locked->game;
            $opponent = Team::find($meta['opponent_team_id'] ?? null);
            if ($game === null || $opponent === null) {
                return ['ok' => false, 'error' => 'game.gov_friendly_invalid'];
            }

            if ($this->hasCompetitionInProgress($game)) {
                return ['ok' => false, 'error' => 'game.gov_friendly_invalid'];
            }

            $amount = (int) ($meta['amount'] ?? 0);
            $date = ($game->current_date ?? now())->copy()->addDays(21);

            // Ensure the FRIENDLY competition exists (FK constraint).
            DB::table('competitions')->updateOrInsert(
                ['id' => 'FRIENDLY'],
                [
                    'name' => 'game.friendly_competition_name',
                    'country' => 'XX',
                    'tier' => 0,
                    'type' => 'cup',
                    'handler_type' => 'friendly',
                    'season' => (string) ($game->season ?? ''),
                ]
            );

            GameMatch::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'competition_id' => 'FRIENDLY',
                'round_number' => 1,
                'round_name' => __('game.gov_friendly_round_name', ['government' => $meta['government'] ?? '']),
                'home_team_id' => $game->team_id,
                'away_team_id' => $opponent->id,
                'scheduled_date' => $date,
                'home_score' => null,
                'away_score' => null,
                'played' => false,
                'neutral_venue_name' => null,
                'government_sponsored' => true,
            ]);

            $game->update([
                'federation_budget' => (int) ($game->federation_budget ?? 0) + $amount,
            ]);

            $meta['status'] = 'accepted';
            $locked->update(['metadata' => $meta, 'read_at' => now()]);

            $this->notifications->create(
                $game,
                GameNotification::TYPE_GOVERNMENT_FRIENDLY_RESULT,
                __('game.gov_friendly_accepted_title'),
                __('game.gov_friendly_accepted_body', [
                    'opponent' => $opponent->name,
                    'amount' => number_format($amount, 0, ',', '.'),
                    'date' => $date->format('d/m/Y'),
                ]),
                GameNotification::PRIORITY_INFO,
                ['government' => $meta['government'] ?? '', 'amount' => $amount],
                'check',
            );

            return ['ok' => true, 'error' => null];
        });
    }

    public function reject(GameNotification $notification): void
    {
        $meta = $notification->metadata ?? [];
        $meta['status'] = 'rejected';
        $notification->update(['metadata' => $meta, 'read_at' => now()]);
    }

    /**
     * Pick a decent friendly opponent: another national team, not the
     * player's own.
     */
    private function pickOpponent(Game $game): ?Team
    {
        return Team::where('type', 'national')
            ->where('id', '!=', $game->team_id)
            ->inRandomOrder()
            ->first();
    }
}
