<?php

declare(strict_types=1);

namespace App\Modules\Stadium\Services;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Venue organization for national-team friendlies.
 *
 * To play a national-team friendly the user must ORGANIZE the venue:
 *  - National stadium (default): always available.
 *  - Club stadium (women's home ground, e.g. Antonio Puchades): the owning
 *    club must accept. If the user manages that club (dual mode), they
 *    decide via notification; otherwise the AI club decides.
 *  - Men's big stadium (e.g. Mestalla, Camp Nou): the men's club (always AI)
 *    decides based on match importance. The women's club has NO say here
 *    (Valencia can't block Mestalla, Barça can't block Camp Nou).
 *  - Neutral ground: always available fallback, smaller gate revenue.
 */
class NationalVenueRequestService
{
    /**
     * Generic neutral venue used when no club accepts (or as provisional
     * venue while a request is pending). Small capacity => less revenue.
     */
    public const NEUTRAL_VENUE_NAME = 'Campo neutral — Ciudad del Fútbol';

    public const NEUTRAL_VENUE_CAPACITY = 8000;

    /**
     * Women's club home grounds that can be requested, grouped for the UI.
     * Only clubs with a real home ground (stadium_name set).
     *
     * @return Collection<int, array{team_id: string, team_name: string, stadium: string, capacity: int, country: string|null}>
     */
    public function clubStadiums(): Collection
    {
        return Team::where('type', 'club')
            ->where('is_placeholder', false)
            ->whereNotNull('stadium_name')
            ->where('stadium_name', '!=', '')
            ->orderBy('country')
            ->orderBy('name')
            ->get(['id', 'name', 'country', 'stadium_name', 'stadium_seats'])
            ->map(fn (Team $t) => [
                'team_id' => $t->id,
                'team_name' => $t->name,
                'stadium' => $t->stadium_name,
                'capacity' => (int) $t->stadium_seats,
                'country' => $t->country,
            ]);
    }

    /**
     * Men's big stadiums that can be requested (always AI-decided).
     *
     * @return list<array{stadium: string, capacity: int, club: string, womens_team: string}>
     */
    public function mensStadiums(): array
    {
        $path = base_path('data/mens_stadiums.json');
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return collect($data['stadiums'] ?? [])
            ->map(fn (array $row) => [
                'stadium' => $row['stadium'],
                'capacity' => (int) $row['capacity'],
                'club' => $row['club'],
                'womens_team' => $row['womens_team'],
            ])
            ->sortBy('stadium')
            ->values()
            ->all();
    }

    /**
     * Does the user of this national-team game manage the given club team
     * (dual mode: club game linked as partner)?
     */
    public function userManagesClub(Game $nationalGame, string $clubTeamId): bool
    {
        $partner = $nationalGame->dualPartner();

        return $partner !== null && $partner->team_id === $clubTeamId;
    }

    /**
     * AI club decision for a national-team venue request.
     *
     * @return array{accepted: bool, excuse: string|null}
     */
    public function evaluateClubRequest(Team $clubTeam, Team $nationalTeam, Team $opponent): array
    {
        $acceptChance = 55;

        // Prestigious national teams are attractive for the club's image.
        $bigNations = ['Spain', 'United States', 'England', 'Germany', 'France', 'Brazil'];
        if (in_array($nationalTeam->name, $bigNations, true)) {
            $acceptChance += 20;
        }

        // Attractive opponent => more media attention.
        if (in_array($opponent->name, $bigNations, true)) {
            $acceptChance += 10;
        }

        // Same country as the club => local pride.
        if (($clubTeam->country ?? null) !== null && $clubTeam->country === ($nationalTeam->country ?? null)) {
            $acceptChance += 10;
        }

        $accepted = mt_rand(1, 100) <= min(95, $acceptChance);

        return [
            'accepted' => $accepted,
            'excuse' => $accepted ? null : $this->randomExcuse($clubTeam->name),
        ];
    }

    /**
     * AI men's club decision for a big-stadium request. Big stadiums are
     * harder to get: the match must feel important.
     *
     * @return array{accepted: bool, excuse: string|null}
     */
    public function evaluateMensRequest(string $mensClub, Team $nationalTeam, Team $opponent): array
    {
        $importance = mt_rand(0, 20);

        $bigNations = ['Spain', 'United States', 'England', 'Germany', 'France', 'Brazil'];
        if (in_array($nationalTeam->name, $bigNations, true)) {
            $importance += 25;
        }
        if (in_array($opponent->name, $bigNations, true)) {
            $importance += 30;
        }

        $accepted = $importance >= 50;

        return [
            'accepted' => $accepted,
            'excuse' => $accepted ? null : $this->randomMensExcuse(),
        ];
    }

    /**
     * Excuse from a women's club when it rejects a stadium request.
     */
    public function randomExcuse(string $clubName): string
    {
        $excuses = [
            'venue_excuse_maintenance',
            'venue_excuse_grass',
            'venue_excuse_event',
            'venue_excuse_reserve',
            'venue_excuse_calendar',
        ];

        return $excuses[array_rand($excuses)];
    }

    /**
     * Excuse from a men's club when it rejects a big-stadium request.
     */
    public function randomMensExcuse(): string
    {
        $excuses = [
            'venue_excuse_mens_laliga',
            'venue_excuse_mens_grass',
            'venue_excuse_mens_concert',
            'venue_excuse_mens_works',
        ];

        return $excuses[array_rand($excuses)];
    }
}
