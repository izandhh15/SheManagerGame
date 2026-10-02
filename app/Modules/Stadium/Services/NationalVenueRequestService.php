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
     * National-team stadium catalogue (data/stadiums.json).
     *
     * NOTE: the file is an object {"stadiums": [...]}, NOT a top-level
     * array — collect() on the raw decode gives a single 'stadiums' group
     * and every downstream access ($s['stadium'], number_format($s['capacity']))
     * blows up into a 500. Always go through this method.
     *
     * @return Collection<int, array{stadium: string, city: string|null, capacity: int, country: string, national_team: bool}>
     */
    public function nationalStadiums(): Collection
    {
        $path = base_path('data/stadiums.json');
        if (! is_file($path)) {
            return collect();
        }

        $data = json_decode(@file_get_contents($path), true);

        return collect($data['stadiums'] ?? [])
            ->map(fn (array $row) => [
                'stadium' => (string) ($row['stadium'] ?? ''),
                'city' => $row['city'] ?? null,
                'capacity' => (int) ($row['capacity'] ?? 0),
                'country' => (string) ($row['country'] ?? ''),
                'national_team' => (bool) ($row['national_team'] ?? false),
            ])
            ->filter(fn (array $row) => $row['stadium'] !== '')
            ->values();
    }

    /**
     * Sensible default national stadium for the user's national team:
     * the country's national-team ground when the catalogue has one,
     * otherwise the first national-team ground overall, otherwise the
     * first stadium in the catalogue.
     */
    public function defaultNationalStadium(Collection $stadiums, ?Team $nationalTeam): ?array
    {
        if ($stadiums->isEmpty()) {
            return null;
        }

        // Raw DB name: the `name` accessor translates national teams
        // (Spain → España), but the catalogue is keyed by the raw name.
        $teamName = strtolower(trim((string) ($nationalTeam?->getRawOriginal('name') ?? '')));
        if ($teamName !== '') {
            $home = $stadiums->first(
                fn (array $s) => strtolower($s['country']) === $teamName && $s['national_team']
            ) ?? $stadiums->first(
                fn (array $s) => strtolower($s['country']) === $teamName
            );
            if ($home) {
                return $home;
            }
        }

        return $stadiums->first(fn (array $s) => $s['national_team'])
            ?? $stadiums->first();
    }

    /**
     * Federation stadiums for a national team: ONLY grounds from the
     * team's own country (Spain → Spanish stadiums, default La Cartuja).
     * Falls back to the full catalogue when the country has none.
     *
     * @return Collection<int, array{stadium: string, city: ?string, capacity: int, country: string, national_team: bool}>
     */
    public function federationStadiums(?Team $nationalTeam): Collection
    {
        $all = $this->nationalStadiums();
        $country = strtolower(trim((string) ($nationalTeam?->getRawOriginal('name') ?? '')));

        if ($country === '') {
            return $all;
        }

        $filtered = $all
            ->filter(fn (array $s) => strtolower($s['country']) === $country)
            ->values();

        return $filtered->isNotEmpty() ? $filtered : $all;
    }

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
                'team_name' => $t->getRawOriginal('name'),
                'stadium' => $t->stadium_name,
                'capacity' => (int) $t->stadium_seats,
                'country' => $t->country,
            ])
            // Defensive: never show the same club twice (stale duplicate rows).
            ->unique('team_name')
            ->values();
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
                'key' => $row['stadium'] . '|' . ($row['club'] ?? $row['owner'] ?? ''),
                'stadium' => $row['stadium'],
                'capacity' => (int) $row['capacity'],
                'club' => $row['club'],
                'womens_team' => $row['womens_team'] ?? null,
                // The owner names the price: each men's club sets its own
                // per-match fee; municipal grounds (La Cartuja...) belong to
                // the city council and anyone can rent them.
                'owner' => $row['owner'] ?? $row['club'],
                'rental_price' => (int) ($row['rental_price'] ?? 0),
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
     * A bigger offer (€) increases the acceptance chance: +5 points per
     * €250K offered, capped at +30.
     *
     * @return array{accepted: bool, excuse: string|null}
     */
    public function evaluateClubRequest(Team $clubTeam, ?Team $nationalTeam, ?Team $opponent, ?int $offerEuros = null): array
    {
        $acceptChance = 55;

        // Prestigious national teams are attractive for the club's image.
        $bigNations = ['Spain', 'United States', 'England', 'Germany', 'France', 'Brazil'];
        if ($nationalTeam && in_array($nationalTeam->name, $bigNations, true)) {
            $acceptChance += 20;
        }

        // Attractive opponent => more media attention.
        if ($opponent && in_array($opponent->name, $bigNations, true)) {
            $acceptChance += 10;
        }

        // Same country as the club => local pride.
        if ($nationalTeam && ($clubTeam->country ?? null) !== null && $clubTeam->country === ($nationalTeam->country ?? null)) {
            $acceptChance += 10;
        }

        // Money talks: the federation's offer sweetens the deal.
        if ($offerEuros !== null && $offerEuros > 0) {
            $acceptChance += min(30, (int) floor($offerEuros / 250000) * 5);
        }

        $accepted = mt_rand(1, 100) <= min(95, $acceptChance);

        return [
            'accepted' => $accepted,
            'excuse' => $accepted ? null : $this->randomExcuse($clubTeam->name),
        ];
    }

    /**
     * AI men's club decision for a big-stadium request. Big stadiums are
     * harder to get: the match must feel important. A bigger offer helps:
     * +5 importance points per €500K offered, capped at +30.
     *
     * @return array{accepted: bool, excuse: string|null}
     */
    /**
     * The men's club decides whether to host a national team. The price is
     * theirs (rental_price in the catalogue) — the federation pays it or
     * walks away. Acceptance depends on the fixture's profile, not on the
     * offer: big nations and marquee opponents get a yes.
     */
    /**
     * The men's club (always AI) evaluates the request. A null club means a
     * municipal ground (e.g. La Cartuja): the city council rents to whoever
     * pays, so the request is always accepted.
     */
    public function evaluateMensRequest(?string $mensClub, ?Team $nationalTeam, ?Team $opponent): array
    {
        if ($mensClub === null || $mensClub === '') {
            return ['accepted' => true, 'excuse' => null];
        }

        $importance = mt_rand(0, 20);

        $bigNations = ['Spain', 'United States', 'England', 'Germany', 'France', 'Brazil'];
        if ($nationalTeam && in_array($nationalTeam->name, $bigNations, true)) {
            $importance += 25;
        }
        if ($opponent && in_array($opponent->name, $bigNations, true)) {
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
            'game.venue_excuse_derby',
            'game.venue_excuse_pitch',
            'game.venue_excuse_concert',
            'game.venue_excuse_maintenance',
            'game.venue_excuse_academy',
            'game.venue_excuse_generic',
        ];

        return $excuses[array_rand($excuses)];
    }

    /**
     * Excuse from a men's club when it rejects a big-stadium request.
     */
    public function randomMensExcuse(): string
    {
        $excuses = [
            'game.venue_mens_excuse_laliga',
            'game.venue_mens_excuse_grass',
            'game.venue_mens_excuse_concert',
            'game.venue_mens_excuse_maintenance',
            'game.venue_mens_excuse_reserve',
        ];

        return $excuses[array_rand($excuses)];
    }
}
