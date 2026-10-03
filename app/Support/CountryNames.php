<?php

namespace App\Support;

/**
 * ISO-ish country code → English country name map, shared by the
 * national-team venue/stage screens and the club preseason stage.
 *
 * (Extracted from the duplicated private maps in ShowScheduleFriendly /
 * ShowNationalVenues so every screen spells countries the same way.)
 */
class CountryNames
{
    public const MAP = [
        'ES' => 'Spain',
        'EN' => 'England',
        'GB-ENG' => 'England',
        'FR' => 'France',
        'DE' => 'Germany',
        'IT' => 'Italy',
        'PT' => 'Portugal',
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
        'CH' => 'Switzerland',
        'AT' => 'Austria',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'DK' => 'Denmark',
        'FI' => 'Finland',
        'IS' => 'Iceland',
        'IE' => 'Republic of Ireland',
        'GB-SCT' => 'Scotland',
        'GB-WLS' => 'Wales',
        'GB-NIR' => 'Northern Ireland',
        'PL' => 'Poland',
        'CZ' => 'Czechia',
        'SK' => 'Slovakia',
        'HU' => 'Hungary',
        'RO' => 'Romania',
        'BG' => 'Bulgaria',
        'GR' => 'Greece',
        'HR' => 'Croatia',
        'RS' => 'Serbia',
        'SI' => 'Slovenia',
        'BA' => 'Bosnia and Herzegovina',
        'AL' => 'Albania',
        'MK' => 'North Macedonia',
        'ME' => 'Montenegro',
        'TR' => 'Turkey',
        'UA' => 'Ukraine',
        'RU' => 'Russia',
        'BY' => 'Belarus',
        'AR' => 'Argentina',
        'BR' => 'Brazil',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'UY' => 'Uruguay',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'VE' => 'Venezuela',
        'EC' => 'Ecuador',
        'BO' => 'Bolivia',
        'MX' => 'Mexico',
        'US' => 'USA',
        'CA' => 'Canada',
        'CR' => 'Costa Rica',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'CN' => 'China',
        'AU' => 'Australia',
        'NZ' => 'New Zealand',
        'ZA' => 'South Africa',
        'NG' => 'Nigeria',
        'GH' => 'Ghana',
        'CM' => 'Cameroon',
        'SN' => 'Senegal',
        'CI' => 'Ivory Coast',
        'MA' => 'Morocco',
        'DZ' => 'Algeria',
        'TN' => 'Tunisia',
        'EG' => 'Egypt',
        'QA' => 'Qatar',
        'AE' => 'United Arab Emirates',
    ];

    /**
     * Popular preseason stage destinations, in a sensible display order.
     *
     * @return list<string>
     */
    public const STAGE_DESTINATIONS = [
        'Spain',
        'Portugal',
        'England',
        'France',
        'Germany',
        'Italy',
        'Netherlands',
        'Belgium',
        'Austria',
        'Switzerland',
        'Turkey',
        'USA',
        'Mexico',
        'Brazil',
        'Japan',
        'Qatar',
        'United Arab Emirates',
    ];

    public static function name(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::MAP[strtoupper($code)] ?? null;
    }
}
