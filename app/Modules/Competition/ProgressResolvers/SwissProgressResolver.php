<?php

namespace App\Modules\Competition\ProgressResolvers;

use App\Models\GameStanding;
use App\Modules\Competition\Contracts\ProgressResolver;
use App\Modules\Competition\DTOs\QualificationOutcome;

class SwissProgressResolver implements ProgressResolver
{
    /**
     * League-phase qualification cuts per swiss-format competition, keyed by
     * competition id. Only the UWCL still runs a league phase (28 teams:
     * 1–8 direct to R16, 9–24 playoff, 25–28 eliminated); the Europa Cup is
     * a pure knockout since 2026-27 and never reaches this resolver (the
     * factory keys resolvers by handler_type). Unknown ids fall back to the
     * UCL cuts.
     */
    private const CUTS = [
        'UCL' => ['direct' => 8, 'playoff' => 24],
    ];

    public function resolve(string $gameId, string $competitionId, GameStanding $standing): ?QualificationOutcome
    {
        // A standing without a position hasn't been ordered yet — never
        // report it as qualified (in PHP, null <= 8 evaluates to true).
        if ($standing->position === null) {
            return null;
        }

        $cuts = self::CUTS[$competitionId] ?? self::CUTS['UCL'];

        if ($standing->position <= $cuts['direct']) {
            return QualificationOutcome::advanced('cup.swiss_direct_r16');
        }

        if ($standing->position <= $cuts['playoff']) {
            return QualificationOutcome::playoff('cup.swiss_knockout_playoff');
        }

        return QualificationOutcome::eliminated('cup.swiss_eliminated');
    }
}
