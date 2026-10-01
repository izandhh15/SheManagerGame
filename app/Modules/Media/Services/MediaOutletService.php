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

        // 2. Country outlets.
        $country = $this->countryCode($game);
        if ($country && isset($data['countries'][$country])) {
            $outlets = array_merge($outlets, $data['countries'][$country]);
        }

        // 3. Fallback.
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

    private function teamName(Game $game): ?string
    {
        try {
            $team = $game->team;
            return $team?->name;
        } catch (\Throwable) {
            return null;
        }
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
