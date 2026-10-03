<?php

namespace App\Modules\Media\Services;

use App\Models\Game;

/**
 * Resolves which media outlets cover a given game, for the news feed.
 *
 * Priority: team-specific outlets (e.g. València Femení → Tribuna Deportiva,
 * El Nostre Equip, SUPERDeporte) → country outlets (e.g. Spain → MARCA, AS,
 * SPORT) → generic default outlets.
 */
class MediaOutletService
{
    private ?array $data = null;

    private function data(): array
    {
        if ($this->data === null) {
            $path = base_path('data/media_outlets.json');
            $this->data = file_exists($path)
                ? (json_decode(file_get_contents($path), true) ?: [])
                : [];
        }

        return $this->data;
    }

    /**
     * All outlets relevant for this game (team first, then country).
     *
     * @return list<string>
     */
    public function outletsFor(Game $game): array
    {
        $data = $this->data();
        $outlets = [];

        // 1. Team-specific outlets (match by team name keywords).
        $teamName = $this->teamName($game);
        foreach ($data['teams'] ?? [] as $key => $list) {
            if ($teamName && stripos($teamName, $key) !== false) {
                $outlets = array_merge($outlets, $list);
            }
        }

        // 2. Country outlets. The JSON keys are FIFA 3-letter codes (ESP, ENG)
        // while the game carries ISO 2-letter (es, ad) — map them.
        $country = $this->countryCode($game);
        $fifa = $country ? ($this->fifaMap()[$country] ?? null) : null;
        foreach ([$country, $fifa] as $key) {
            if ($key && isset($data['countries'][$key])) {
                $outlets = array_merge($outlets, $data['countries'][$key]);
            }
        }

        // 3. International/digital media (433, OneFootball, ...).
        if (! empty($data['international'])) {
            $outlets = array_merge($outlets, $data['international']);
        }

        // 4. Fallback.
        if (empty($outlets)) {
            $outlets = $data['default'] ?? ['EFE Deportes'];
        }

        return array_values(array_unique($outlets));
    }

    /**
     * Pick a random outlet for a news item.
     */
    public function randomOutlet(Game $game): string
    {
        $outlets = $this->outletsFor($game);

        return $outlets[array_rand($outlets)];
    }

    /**
     * Deterministic outlet pick for a given seed (e.g. game id + round), so
     * the news feed doesn't flicker across page loads.
     */
    public function deterministicOutlet(Game $game, string $seed): string
    {
        $outlets = $this->outletsFor($game);

        return $outlets[crc32($seed) % max(1, count($outlets))] ?? 'EFE Deportes';
    }

    /**
     * Branding info for an outlet: logo URL (local file) + brand colour.
     * Outlets without a downloaded logo fall back to a coloured text badge.
     *
     * @return array{name: string, logo: ?string, color: string}
     */
    public function outletInfo(string $name): array
    {
        $outlet = $this->data()['outlets'][$name] ?? null;

        return [
            'name' => $name,
            'logo' => !empty($outlet['logo'])
                ? asset('images/media-logos/'.$outlet['logo'])
                : null,
            'color' => $outlet['color'] ?? '#475569',
        ];
    }

    private function teamName(Game $game): ?string
    {
        try {
            $team = $game->team;
            return $team?->name;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * ISO 2-letter (game) → FIFA 3-letter (media_outlets.json keys).
     */
    private function fifaMap(): array
    {
        return [
            'ES' => 'ESP', 'AD' => 'AND', 'AR' => 'ARG', 'BR' => 'BRA',
            'DE' => 'DEU', 'GB-ENG' => 'ENG', 'FR' => 'FRA', 'IT' => 'ITA',
            'MX' => 'MEX', 'NL' => 'NED', 'PT' => 'POR', 'CH' => 'SUI',
            'US' => 'USA',
        ];
    }

    private function countryCode(Game $game): ?string
    {
        try {
            // National-team games carry the country on the team; club games
            // carry it on the competition's country.
            $team = $game->team;
            if (($team?->type ?? 'club') === 'national' && $team->country) {
                return strtoupper($team->country);
            }
            $competition = $game->competition;
            return $competition?->country ? strtoupper($competition->country) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
