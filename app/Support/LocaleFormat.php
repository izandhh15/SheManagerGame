<?php

namespace App\Support;

/**
 * Locale-aware formatting helpers (i18n-b review).
 *
 * Centralises the bits of locale logic that used to be hardcoded in
 * Blade views / JS: ordinal suffixes and the BCP-47 tag used by
 * Intl.NumberFormat in Alpine components.
 */
class LocaleFormat
{
    /**
     * League-table style ordinal for a 1-based position, e.g. "1º",
     * "2nd", "3e", "4." depending on the current app locale.
     */
    public static function ordinal(int $position): string
    {
        return match (app()->getLocale()) {
            'en' => $position.self::englishOrdinalSuffix($position),
            'fr' => $position.($position === 1 ? 're' : 'e'),
            'de', 'pt' => $position.'.',
            // es (and any other locale): masculine ordinal indicator.
            default => $position.'º',
        };
    }

    /**
     * BCP-47 locale tag for JS Intl formatting. English uses en-IE
     * (euro zone) because the whole game economy is in euros.
     */
    public static function jsLocale(): string
    {
        return match (app()->getLocale()) {
            'es' => 'es-ES',
            'en' => 'en-IE',
            'de' => 'de-DE',
            'fr' => 'fr-FR',
            'pt' => 'pt-PT',
            default => 'es-ES',
        };
    }

    private static function englishOrdinalSuffix(int $n): string
    {
        if ($n % 100 >= 11 && $n % 100 <= 13) {
            return 'th';
        }

        return match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
