<?php

namespace App\Support;

use App\Models\Competition;
use Illuminate\Support\Facades\Storage;

/**
 * Competition logo badges (SVG) served from public/img/competitions/{ID}.svg.
 *
 * Every seeded competition has a clean, branded badge. Unknown ids fall back
 * to a generic badge so the UI never renders a broken image.
 */
class CompetitionLogos
{
    /**
     * Public URL of the logo for a competition id (e.g. 'BRA1', 'UCL').
     * Returns null only when even the generic fallback is missing.
     */
    public static function url(?string $competitionId): ?string
    {
        $disk = Storage::disk('assets');

        if ($competitionId) {
            $path = "img/competitions/{$competitionId}.svg";
            if (file_exists(public_path($path))) {
                return $disk->url($path);
            }
        }

        $fallback = 'img/competitions/GENERIC.svg';
        if (file_exists(public_path($fallback))) {
            return $disk->url($fallback);
        }

        return null;
    }

    public static function urlFor(?Competition $competition): ?string
    {
        return self::url($competition?->id);
    }
}
