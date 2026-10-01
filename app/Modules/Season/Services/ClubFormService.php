<?php

namespace App\Modules\Season\Services;

/**
 * Generates plausible club-season stats for national-team candidates.
 *
 * In national-team mode the game does not simulate club football, so there
 * is no real "club form" data. This service synthesizes deterministic
 * per-player club stats (minutes, appearances, goals, assists, clean
 * sheets, average rating) from the player's overall and position, so the
 * squad picker can show "rendimiento con su club" when making a call-up.
 *
 * Determinism: stats derive from a hash of (player_id + season), so they
 * are stable across page loads but vary between players and seasons.
 * Season progress (0.0-1.0) scales the numbers, so mid-season call-ups show
 * partial campaigns.
 */
final class ClubFormService
{
    /**
     * @return array{appearances:int, minutes:int, goals:int, assists:int, clean_sheets:int, rating:float}
     */
    public static function statsFor(
        string $playerId,
        int $overall,
        string $positionGroup,
        string $season = '2026',
        float $progress = 1.0,
    ): array {
        $seed = hexdec(substr(md5($playerId . '|' . $season . '|clubform'), 0, 7));
        $rand = fn (int $min, int $max) => $min + ($seed % max(1, $max - $min + 1));

        // Base appearances scale with overall: stars play ~30 league games,
        // fringe players ~8. Jittered by the deterministic seed.
        $baseApps = match (true) {
            $overall >= 88 => 30,
            $overall >= 83 => 27,
            $overall >= 78 => 23,
            $overall >= 73 => 17,
            $overall >= 68 => 12,
            default => 8,
        };
        $appearances = (int) round(($baseApps + $rand(-3, 3)) * $progress);
        $appearances = max(0, $appearances);

        // Minutes: starters ~85/game, rotation ~60, fringe ~35.
        $minsPerApp = match (true) {
            $overall >= 83 => 84,
            $overall >= 76 => 68,
            $overall >= 70 => 52,
            default => 34,
        };
        $minutes = $appearances * ($minsPerApp + $rand(-8, 8));
        $minutes = max(0, $minutes);

        // Goals/assists per 90 by position group, scaled by overall.
        $quality = ($overall - 60) / 30; // ~0.3 (60) .. 1.0 (90)
        $per90 = match ($positionGroup) {
            'Forward' => ['g' => 0.55, 'a' => 0.22],
            'Midfielder' => ['g' => 0.18, 'a' => 0.32],
            'Defender' => ['g' => 0.06, 'a' => 0.10],
            default => ['g' => 0.0, 'a' => 0.02], // Goalkeeper
        };
        $nineties = $minutes / 90;
        $goals = (int) round($nineties * $per90['g'] * (0.5 + $quality) + $rand(0, 2) * $quality);
        $assists = (int) round($nineties * $per90['a'] * (0.5 + $quality) + $rand(0, 2) * $quality);

        // Clean sheets for GK/DEF (share of appearances).
        $csRate = match ($positionGroup) {
            'Goalkeeper' => 0.38,
            'Defender' => 0.32,
            default => 0.0,
        };
        $cleanSheets = (int) round($appearances * $csRate * (0.7 + 0.6 * $quality));

        // Average rating 6.0-7.8, anchored on overall.
        $rating = round(6.1 + ($overall - 60) * 0.055 + ($rand(0, 20) - 10) / 100, 1);
        $rating = min(8.2, max(5.8, $rating));

        return [
            'appearances' => $appearances,
            'minutes' => $minutes,
            'goals' => $goals,
            'assists' => $assists,
            'clean_sheets' => $cleanSheets,
            'rating' => $rating,
        ];
    }

    /**
     * Season progress 0.0-1.0 from the game's current date.
     * Season runs July 1 → June 30.
     */
    public static function seasonProgress(?string $currentDate): float
    {
        if (! $currentDate) {
            return 1.0;
        }
        try {
            $date = \Carbon\Carbon::parse($currentDate);
        } catch (\Throwable) {
            return 1.0;
        }
        $year = $date->month >= 7 ? $date->year : $date->year - 1;
        $start = \Carbon\Carbon::create($year, 7, 1);
        $end = \Carbon\Carbon::create($year + 1, 6, 30);
        if ($date->lessThan($start)) {
            return 0.0;
        }
        if ($date->greaterThan($end)) {
            return 1.0;
        }
        return round($start->diffInDays($date) / max(1, $start->diffInDays($end)), 2);
    }
}
