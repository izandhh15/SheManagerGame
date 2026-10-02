<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Services;

use App\Models\ClubProfile;

/**
 * Sponsor levels by club reputation (F9, 0.3.9): big clubs attract
 * international sponsors, small clubs only local ones. The reputation
 * comes from ClubProfile::REPUTATION_* (TeamReputation under the hood).
 */
class SponsorLevelService
{
    public const LEVEL_LOCAL = 'local';
    public const LEVEL_REGIONAL = 'regional';
    public const LEVEL_NATIONAL = 'nacional';
    public const LEVEL_INTERNATIONAL = 'internacional';

    /**
     * Which sponsor levels a club reputation can attract.
     *
     * @return list<string>
     */
    public function eligibleLevels(string $reputation): array
    {
        return match ($reputation) {
            ClubProfile::REPUTATION_ELITE,
            ClubProfile::REPUTATION_CONTINENTAL => [
                self::LEVEL_INTERNATIONAL,
                self::LEVEL_NATIONAL,
            ],
            ClubProfile::REPUTATION_ESTABLISHED => [
                self::LEVEL_NATIONAL,
                self::LEVEL_REGIONAL,
            ],
            ClubProfile::REPUTATION_MODEST => [
                self::LEVEL_NATIONAL,
                self::LEVEL_REGIONAL,
                self::LEVEL_LOCAL,
            ],
            default => [ // REPUTATION_LOCAL and anything unknown
                self::LEVEL_REGIONAL,
                self::LEVEL_LOCAL,
            ],
        };
    }

    /**
     * The highest sponsor level a club can attract.
     */
    public function topLevel(string $reputation): string
    {
        return $this->eligibleLevels($reputation)[0];
    }
}
