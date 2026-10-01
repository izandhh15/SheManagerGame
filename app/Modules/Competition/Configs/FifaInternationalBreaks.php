<?php

declare(strict_types=1);

namespace App\Modules\Competition\Configs;

/**
 * FIFA women's international match windows.
 *
 * Source: FIFA Women's International Match Calendar 2026-2029 (FIFA, 1 March 2026).
 * During these windows clubs must release players to national teams, so no
 * domestic league matchdays should be scheduled inside them.
 *
 * League schedules (data/2026/<COMP>/schedule.json) must leave these weeks empty.
 */
final class FifaInternationalBreaks
{
    /**
     * @return list<array{start: string, end: string, label: string}>
     */
    public static function forSeason(string $season): array
    {
        return match ($season) {
            '2026' => [
                ['start' => '2026-09-01', 'end' => '2026-09-09', 'label' => 'Parón FIFA'],
                ['start' => '2026-10-05', 'end' => '2026-10-13', 'label' => 'Parón FIFA'],
                ['start' => '2026-11-24', 'end' => '2026-12-05', 'label' => 'Parón FIFA'],
                ['start' => '2027-02-23', 'end' => '2027-03-06', 'label' => 'Parón FIFA'],
                ['start' => '2027-04-13', 'end' => '2027-04-24', 'label' => 'Parón FIFA'],
                // 7-15 June 2027: post-season window, no club football anyway.
                // 24 June - 25 July 2027: FIFA Women's World Cup 2027 (off-season).
            ],
            default => [],
        };
    }

    public static function isBreak(string $season, string $date): bool
    {
        foreach (self::forSeason($season) as $window) {
            if ($date >= $window['start'] && $date <= $window['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * The window containing $date, if any. Used together with
     * upcomingWithin() so the squad picker is also prompted while a
     * break is already underway (not only in the days before it).
     *
     * @return array{start: string, end: string, label: string}|null
     */
    public static function currentWindow(string $season, string $date): ?array
    {
        foreach (self::forSeason($season) as $window) {
            if ($date >= $window['start'] && $date <= $window['end']) {
                return $window;
            }
        }

        return null;
    }

    /**
     * Next window starting within $daysBefore days from $date.
     * Used to prompt the squad picker a few days before each window.
     *
     * @return array{start: string, end: string, label: string}|null
     */
    public static function upcomingWithin(string $season, string $date, int $daysBefore = 7): ?array
    {
        $cutoff = date('Y-m-d', strtotime($date . " +{$daysBefore} days"));
        foreach (self::forSeason($season) as $window) {
            if ($window['start'] > $date && $window['start'] <= $cutoff) {
                return $window;
            }
        }

        return null;
    }
}
