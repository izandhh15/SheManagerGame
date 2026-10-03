<?php

namespace App\Modules\Manager\Services;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Academy career mode: the manager picks a CLUB (shown with its academy
 * nickname like "La Masía") and starts at the lowest reserve team,
 * earning random promotions up the pyramid towards the first team.
 *
 * Example: FC Barcelona → start at Barça C → random promotion to Barça B
 * → random promotion to first team. The timing is fully random: could be
 * 1 season at C + 1 at B + rest at first team, or 5 seasons at C, etc.
 */
class AcademyCareerService
{
    /**
     * Get all first-team clubs that have at least one filial (reserve team).
     * Returns clubs with their academy nickname for the picker UI.
     *
     * @return Collection<int, array{team: Team, academy_nickname: string|null, lowest_filial: Team}>
     */
    public function getClubsWithAcademies(): Collection
    {
        $academies = $this->getAcademyNicknames();

        // First teams that have at least one child team
        $clubs = Team::whereNull('parent_team_id')
            ->where('type', 'club')
            ->where('is_placeholder', false)
            ->whereExists(function ($query) {
                $query->selectRaw(1)
                    ->from('teams as children')
                    ->whereColumn('children.parent_team_id', 'teams.id');
            })
            ->with('reserveTeam')
            ->orderBy('name')
            ->get();

        return $clubs->map(function (Team $club) use ($academies) {
            $lowestFilial = $this->findLowestFilial($club);

            return [
                'team' => $club,
                'academy_nickname' => $this->resolveAcademyNickname($club->name, $academies),
                'lowest_filial' => $lowestFilial,
                'filial_chain' => $this->getFilialChain($club),
            ];
        })->filter(fn ($item) => $item['lowest_filial'] !== null)->values();
    }

    /**
     * Find the lowest (deepest) filial in the chain.
     * E.g. Barça → B → C returns C.
     *
     * Returns null when the club has no filial at all: getFilialChain()
     * always contains the first team itself, so `->last()` on a lone club
     * would hand back the first team and silently start an "academy
     * career" at the senior side (M37). The null lets InitGame reject
     * the request with `messages.club_has_no_filial`.
     */
    public function findLowestFilial(Team $firstTeam): ?Team
    {
        $chain = $this->getFilialChain($firstTeam);

        return $chain->count() > 1 ? $chain->last() : null;
    }

    /**
     * Get the full filial chain from first team down to the lowest.
     * Returns collection ordered from first team to lowest filial.
     * E.g. [Barça, Barça B, Barça C]
     *
     * @return Collection<int, Team>
     */
    public function getFilialChain(Team $firstTeam): Collection
    {
        $chain = collect([$firstTeam]);
        $current = $firstTeam;

        // Follow the chain down. If a team has multiple children (shouldn't
        // happen in a linear chain, but just in case), pick the one with
        // the deepest subtree.
        while (true) {
            $children = Team::where('parent_team_id', $current->id)
                ->where('is_placeholder', false)
                ->get();

            if ($children->isEmpty()) {
                break;
            }

            // Pick the child with the deepest subtree (lowest in pyramid)
            $deepest = $children->sortByDesc(fn (Team $child) => $this->getDepth($child))->first();
            $chain->push($deepest);
            $current = $deepest;

            // Safety: prevent infinite loops
            if ($chain->count() > 10) {
                break;
            }
        }

        return $chain;
    }

    /**
     * Get the depth of a team in the filial hierarchy (0 = first team).
     */
    private function getDepth(Team $team, int $current = 0): int
    {
        $children = Team::where('parent_team_id', $team->id)
            ->where('is_placeholder', false)
            ->get();

        if ($children->isEmpty()) {
            return $current;
        }

        return $children->map(fn (Team $child) => $this->getDepth($child, $current + 1))->max();
    }

    /**
     * Resolve the academy nickname for a club name.
     * Tries exact match, then case-insensitive, then stripping suffixes.
     */
    public function resolveAcademyNickname(string $clubName, array $academies): ?string
    {
        // Exact match
        if (isset($academies[$clubName])) {
            return $academies[$clubName];
        }

        // Case-insensitive
        $lower = mb_strtolower($clubName);
        foreach ($academies as $key => $nickname) {
            if (mb_strtolower($key) === $lower) {
                return $nickname;
            }
        }

        // Strip common suffixes and try again
        $stripped = preg_replace('/\s+(CF|FC|CD|UD|SD|RCD|RC|AC|SC|AS|SSC|CF$)/iu', '', $clubName);
        $stripped = trim($stripped);
        $lowerStripped = mb_strtolower($stripped);

        foreach ($academies as $key => $nickname) {
            $keyStripped = trim(preg_replace('/\s+(CF|FC|CD|UD|SD|RCD|RC|AC|SC|AS|SSC|CF$)/iu', '', $key));
            if (mb_strtolower($keyStripped) === $lowerStripped) {
                return $nickname;
            }
        }

        return null;
    }

    /**
     * Roll for a promotion at season end. Returns the team to promote to,
     * or null if the manager stays put.
     *
     * The probability is influenced by season performance:
     * - Won the league: 70% chance
     * - Top 3: 50% chance
     * - Mid-table: 25% chance
     * - Bottom/relegated: 5% chance (but you might get fired instead!)
     *
     * If already at the first team, always returns null.
     */
    public function rollForPromotion(Game $game, string $seasonGrade): ?Team
    {
        $currentTeam = $game->team;

        // Already at first team (no parent) — nowhere to go up
        if (!$currentTeam->isReserveTeam()) {
            return null;
        }

        $parent = $currentTeam->parentTeam;
        if (!$parent) {
            return null;
        }

        $probability = match ($seasonGrade) {
            'champion', 'won' => 0.70,
            'excellent', 'top3' => 0.50,
            'good', 'met' => 0.30,
            'poor', 'below' => 0.10,
            default => 0.25,
        };

        // Small random bonus/penalty for drama (±10%)
        $probability += (mt_rand(-10, 10) / 100);
        $probability = max(0.05, min(0.85, $probability));

        if (mt_rand(1, 100) / 100 <= $probability) {
            return $parent;
        }

        return null;
    }

    /**
     * Check if a game is in academy career mode (started from a filial
     * via the academy picker).
     */
    public function isAcademyCareer(Game $game): bool
    {
        return (bool) $game->getAttribute('academy_career_club_id');
    }

    /**
     * Get the original club (first team) for an academy career game.
     */
    public function getAcademyClub(Game $game): ?Team
    {
        $clubId = $game->getAttribute('academy_career_club_id');

        return $clubId ? Team::find($clubId) : null;
    }

    /**
     * Academy nicknames (inlined to avoid config file deploy issues).
     */
    private function getAcademyNicknames(): array
    {
        return [
    // Spain
    'FC Barcelona' => 'La Masía',
    'Real Madrid CF' => 'La Fábrica',
    'Atlético de Madrid' => 'Academia Atlético',
    'Athletic Club' => 'Lezama',
    'Real Sociedad' => 'Zubieta',
    'Valencia CF' => 'Academia VCF',
    'Villarreal CF' => 'Cantera Grogueta',
    'Sevilla FC' => 'Cantera Sevillista',
    'Real Betis Balompié' => 'Cantera Bética',
    'RCD Espanyol' => 'Ciutat Esportiva Dani Jarque',
    'Deportivo ABANCA' => 'Abegondo',
    'Levante UD' => 'Cantera Granota',
    'Madrid CFF' => 'Cantera del Madrid CFF',
    'SD Eibar' => 'Cantera Armera',
    'CA Osasuna' => 'Tajonar',
    'Granada CF' => 'Cantera Nazarí',
    'Sporting de Huelva' => 'Cantera Sportinguista',
    'UD Tenerife' => 'Cantera Tinerfeña',
    'FC Levante Badalona' => 'Cantera Badalonina',

    // England
    'Arsenal' => 'Hale End',
    'Chelsea' => 'Cobham',
    'Manchester City' => 'City Football Academy',
    'Manchester United' => 'Carrington',
    'Liverpool' => 'The Academy',
    'Tottenham Hotspur' => 'Hotspur Way',
    'Everton' => 'Finch Farm',
    'Aston Villa' => 'Bodymoor Heath',
    'Brighton & Hove Albion' => 'American Express Elite Centre',
    'West Ham United' => 'Chadwell Heath',

    // Germany
    'FC Bayern München' => 'FC Bayern Campus',
    'VfL Wolfsburg' => 'Nachwuchsleistungszentrum',
    'Eintracht Frankfurt' => 'Riederwald',
    'Bayer 04 Leverkusen' => 'Kurtekotten',
    'Borussia Dortmund' => 'Hohenbuschei',
    'Turbine Potsdam' => 'Nachwuchsakademie',
    'SC Freiburg' => 'Schönbergstadion Nachwuchs',
    'TSG Hoffenheim' => 'Akademie Hoffenheim',
    'RB Leipzig' => 'RB Nachwuchsakademie',
    '1. FC Köln' => 'Geißbockheim',

    // France
    'Olympique Lyonnais' => 'Académie OL',
    'Paris Saint-Germain' => 'Campus PSG',
    'Paris FC' => 'Académie Paris FC',
    'Montpellier HSC' => 'Centre de Formation',
    'FC Girondins de Bordeaux' => 'Le Haillan',
    'AS Saint-Étienne' => 'L\'Étrat',
    'Dijon FCO' => 'Centre de Formation Dijonnais',
    'FC Nantes' => 'La Jonelière',
    'Stade de Reims' => 'Centre de Vie Raymond Kopa',

    // Italy
    'Juventus' => 'Juventus Academy',
    'Inter' => 'Inter Academy',
    'AC Milan' => 'Milan Academy',
    'AS Roma' => 'Roma Academy',
    'Fiorentina' => 'Viola Park',
    'Napoli' => 'Napoli Academy',
    'Sassuolo' => 'Mapei Football Center',
    'Lazio' => 'Lazio Academy',

    // Portugal
    'SL Benfica' => 'Caixa Futebol Campus',
    'Sporting CP' => 'Alcochete',
    'SC Braga' => 'Cidade Desportiva',
    'FC Porto' => 'Olival',

    // Netherlands
    'Ajax' => 'De Toekomst',
    'PSV' => 'De Herdgang',
    'FC Twente' => 'Tukkers Academy',
    'Feyenoord' => 'Varkenoord',

    // USA (NWSL academies are newer; use club + Academy)
    'OL Reign' => 'Reign Academy',
    'Portland Thorns' => 'Thorns Academy',
    'North Carolina Courage' => 'Courage Academy',
    'Washington Spirit' => 'Spirit Academy',

    // Mexico
    'Tigres UANL' => 'Cantera Felina',
    'Club América' => 'Cantera Azulcrema',
    'Chivas Guadalajara' => 'Cantera Rojiblanca',
    'Rayadas Monterrey' => 'Cantera Rayada',

    // Brazil
    'Corinthians' => 'Terrão',
    'Palmeiras' => 'Academia de Futebol',
    'São Paulo' => 'CFA Laudo Natel',
    'Flamengo' => 'Ninho do Urubu',
    'Santos' => 'CT Rei Pelé',

    // Argentina
    'Boca Juniors' => 'Casa Amarilla',
    'River Plate' => 'River Camp',
    'San Lorenzo' => 'Ciudad Deportiva',
    'Racing Club' => 'Tita Mattiussi',

    // Switzerland
    'FC Zürich' => 'Nachwuchs Campus',
    'Servette FCCF' => 'Académie Servettienne',
    'BSC Young Boys' => 'Nachwuchs YB',
];
    }
}
