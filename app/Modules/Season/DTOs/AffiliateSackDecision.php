<?php

namespace App\Modules\Season\DTOs;

use App\Models\Team;

/**
 * Outcome of the affiliate-career season-end check: the first-team board
 * has sacked its coach and is handing the job to the reserve-side manager.
 */
class AffiliateSackDecision
{
    public const REASON_RELEGATED = 'relegated';
    public const REASON_RELEGATION_ZONE = 'relegation_zone';
    public const REASON_MISSED_OBJECTIVE = 'missed_objective';

    public function __construct(
        public readonly Team $parentTeam,
        public readonly Team $reserveTeam,
        /** Real-world coach name, or null when unknown. */
        public readonly ?string $coachName,
        public readonly int $finalPosition,
        public readonly int $boardTargetPosition,
        public readonly string $boardGoal,
        /** One of the REASON_* constants. */
        public readonly string $reason,
        /** League competition the first team will play in next season. */
        public readonly string $newLeagueId,
    ) {}
}
