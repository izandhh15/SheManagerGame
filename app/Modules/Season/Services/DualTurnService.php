<?php

namespace App\Modules\Season\Services;

use App\Models\Game;
use App\Modules\Match\Services\MatchdayService;

/**
 * Enforces the strict club ⇄ national-team alternation in dual mode.
 *
 * Rule: in a dual pair, the half whose next player match is dated EARLIER
 * must be played first. Trying to open the lineup (or advance) of the half
 * whose next match comes later — while the partner has an earlier pending
 * match — bounces the user to the partner's save instead. The club career
 * stops dead during FIFA windows until the international matches are
 * played; afterwards the user returns to the club, and so on for every
 * international break.
 *
 * Same-day matches are free choice: only a strictly earlier partner match
 * blocks. Fast-mode games are exempt (auto-sim by design).
 */
class DualTurnService
{
    public function __construct(
        private readonly MatchdayService $matchdayService,
    ) {}

    /**
     * The partner game the user must play before this one, or null when
     * this game is free to continue.
     */
    public function mustPlayPartnerFirst(Game $game): ?Game
    {
        $partner = $game->dualPartner();
        if (! $partner) {
            return null;
        }

        $nextMine = $this->matchdayService->getNextPlayerMatch($game);
        $nextPartner = $this->matchdayService->getNextPlayerMatch($partner);

        if (! $nextMine || ! $nextPartner) {
            return null;
        }

        $dateMine = $nextMine->scheduled_date?->format('Y-m-d');
        $datePartner = $nextPartner->scheduled_date?->format('Y-m-d');

        if ($datePartner && $dateMine && $datePartner < $dateMine) {
            return $partner;
        }

        return null;
    }
}
