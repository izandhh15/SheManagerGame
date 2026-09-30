<?php

namespace App\Modules\Competition\Configs;

/**
 * Configuration for the UEFA Women's Euro 2029 qualifying competition
 * (WEUROQ).
 *
 * Same format as the World Cup qualifiers (drawn group of 6, 10-matchday
 * double round-robin, top 2 qualify), but the standings zone points to
 * the Euro 2029 instead of the World Cup.
 */
class WomensEuroQualifyingConfig extends WorldCupQualifyingConfig
{
    public function getStandingsZones(): array
    {
        // Top 2 qualify for Euro 2029 (green); no relegation in a
        // qualifier, so this is the only zone.
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 2,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.euro_qualified',
            ],
        ];
    }
}
