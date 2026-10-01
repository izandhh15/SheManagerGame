<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Competition\Configs\FifaInternationalBreaks;
use Illuminate\Http\Request;

class ShowScheduleFriendly
{
    public const COMPETITION_ID = 'FRIENDLY';

    public const MAX_PER_WINDOW = 2;

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (! $game->isTournamentMode()) {
            abort(404);
        }

        $userTeam = Team::findOrFail($game->team_id);
        $season = $game->season ?? '2026';

        // FIFA windows for this season, each annotated with scheduled count.
        $windows = collect(FifaInternationalBreaks::forSeason($season))->map(function (array $w) use ($gameId) {
            $count = GameMatch::where('game_id', $gameId)
                ->where('competition_id', self::COMPETITION_ID)
                ->where('played', false)
                ->whereDate('scheduled_date', '>=', $w['start'])
                ->whereDate('scheduled_date', '<=', $w['end'])
                ->count();

            return array_merge($w, [
                'scheduled' => $count,
                'remaining' => max(0, self::MAX_PER_WINDOW - $count),
            ]);
        });

        // Rival candidates: every national team except the user's, with a
        // playable templated roster for this season.
        $opponents = Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->where('id', '!=', $game->team_id)
            ->whereExists(function ($q) use ($season) {
                $q->selectRaw('1')
                    ->from('game_player_templates')
                    ->whereColumn('game_player_templates.team_id', 'teams.id')
                    ->where('game_player_templates.season', $season);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'country']);

        // Club teams as additional friendly opponents (e.g. play against
        // Spanish clubs during a stage in Spain). Grouped by country NAME
        // (matching the stadium grouping) for the stage filter.
        $countryNames = $this->countryCodeToNameMap();
        $clubs = Team::where('type', 'club')
            ->where('is_placeholder', false)
            ->orderBy('name')
            ->get(['id', 'name', 'country'])
            ->map(function ($club) use ($countryNames) {
                $club->country_name = $countryNames[strtoupper($club->country ?? '')] ?? $club->country;
                return $club;
            })
            ->groupBy('country_name')
            ->sortKeys();

        // Stadiums grouped by country for the selector.
        $stadiums = collect(json_decode(file_get_contents(base_path('data/stadiums.json')), true) ?? [])
            ->groupBy('country')
            ->sortKeys();

        // Default stadium: the user's national stadium (first entry matching
        // their country code), falling back to the first stadium overall.
        $flat = $stadiums->flatten(1);
        $defaultStadium = $flat->firstWhere('code', strtoupper($userTeam->country ?? ''))
            ?? $flat->first();

        // Already-scheduled friendlies (upcoming only).
        $scheduled = GameMatch::where('game_id', $gameId)
            ->where('competition_id', self::COMPETITION_ID)
            ->where('played', false)
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('scheduled_date')
            ->get();

        return view('schedule-friendly', [
            'game' => $game,
            'userTeam' => $userTeam,
            'windows' => $windows,
            'opponents' => $opponents,
            'clubs' => $clubs,
            'stadiums' => $stadiums,
            'defaultStadium' => $defaultStadium,
            'scheduled' => $scheduled,
            'maxPerWindow' => self::MAX_PER_WINDOW,
        ]);
    }

    /**
     * Map 2-char country codes to the full names used in stadiums.json,
     * so club opponents group under the same country as the stage filter.
     *
     * @return array<string, string>
     */
    private function countryCodeToNameMap(): array
    {
        return [
            'ES' => 'Spain',
            'EN' => 'England',
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
        ];
    }
}
