<?php

namespace App\Modules\Competition\ProgressResolvers;

use App\Models\GameStanding;
use App\Modules\Competition\Contracts\ProgressResolver;
use App\Modules\Competition\DTOs\QualificationOutcome;

class SwissProgressResolver implements ProgressResolver
{
    /**
     * League-phase qualification cuts per swiss-format competition, keyed by
     * competition id. UCL runs 28 teams (1–8 direct to R16, 9–24 playoff,
     * 25–28 eliminated); UEL runs 20 (1–12 direct to R16, 13–20 playoff,
     * nobody eliminated at the league-phase cut — 12 + 4 playoff winners
     * make a clean 16-team R16). Unknown ids fall back to the UCL cuts.
     */
    private const CUTS = [
        'UCL' => ['direct' => 8, 'playoff' => 24],
        'UEL' => ['direct' => 12, 'playoff' => 20],
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
