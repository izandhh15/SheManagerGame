<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Marketing stats for the landing page, computed from the real game data
 * instead of hardcoded claims.
 *
 * Methodology (documented so the numbers stay checkable):
 * - countries:    distinct country codes across data/2026 club data
 * - competitions: concrete competition config classes (excludes the
 *                 DefaultLeague fallback and FifaInternationalBreaks, which
 *                 is not a competition)
 * - teams:        unique clubs (by transfermarktId/name) + national teams
 *                 from NAT.json ("equipos y selecciones")
 * - players:      club players + national-team players ("jugadoras reales")
 *
 * Cached for a week; the underlying data only changes between deploys.
 */
class LandingStats
{
    /**
     * @return array{countries: int, competitions: int, teams: int, players: int}
     */
    public static function get(): array
    {
        return Cache::remember('landing-stats', 7 * 24 * 3600, function (): array {
            $teams = [];
            $players = 0;
            $countries = [];

            foreach (glob(base_path('data/2026/*/teams.json')) ?: [] as $file) {
                $data = json_decode((string) file_get_contents($file), true);
                if (! is_array($data)) {
                    continue;
                }

                foreach ($data['clubs'] ?? [] as $club) {
                    $teams[$club['transfermarktId'] ?? $club['name']] = true;
                    $players += count($club['players'] ?? []);
                    $country = $club['country'] ?? $club['country_code'] ?? null;
                    if ($country) {
                        $countries[$country] = true;
                    }
                }
            }

            // National teams live in data/2026/NAT.json (same shape, one file
            // instead of one dir per competition).
            $natFile = base_path('data/2026/NAT.json');
            if (is_file($natFile)) {
                $nat = json_decode((string) file_get_contents($natFile), true);
                foreach (is_array($nat) ? ($nat['clubs'] ?? []) : [] as $club) {
                    $teams['nat:'.($club['transfermarktId'] ?? $club['name'])] = true;
                    $players += count($club['players'] ?? []);
                }
            }

            $competitions = 0;
            foreach (glob(app_path('Modules/Competition/Configs/*Config.php')) ?: [] as $file) {
                $base = basename($file, '.php');
                if ($base === 'DefaultLeagueConfig') {
                    continue;
                }
                $competitions++;
            }

            return [
                'countries' => count($countries),
                'competitions' => $competitions,
                'teams' => count($teams),
                'players' => $players,
            ];
        });
    }
}
