<?php

namespace App\Modules\Competition\Services;

use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Generates knockout bracket matchups for group-stage + knockout tournaments.
 *
 * Originally built for the FIFA World Cup 2026 (men, retired):
 * 48 teams, 12 groups of 4 — top 2 per group + 8 best 3rd-place teams
 * advance (32 total): R32 → R16 → QF → SF → 3rd place → Final.
 *
 * Now generalized per competition id:
 * - WC2026 keeps the legacy behaviour (third-place qualifiers, fixed
 *   FIFA lookup table, data/2025/WC2026/bracket.json).
 * - Women's finals (WWCU27, WEURO, ...) load their own
 *   data/2026/{id}/bracket.json; only the top 2 per group advance and
 *   group-position slots ("1A", "2B") in the first knockout round are
 *   resolved from the group standings. A competition whose bracket has
 *   no "third_place" key simply skips that match (e.g. the Euros).
 */
class WorldCupKnockoutGenerator
{
    public const ROUND_OF_32 = 1;
    public const ROUND_OF_16 = 2;
    public const ROUND_QUARTER_FINALS = 3;
    public const ROUND_SEMI_FINALS = 4;
    public const ROUND_THIRD_PLACE = 5;
    public const ROUND_FINAL = 6;

    // Bracket slot indices in the third-place table: [1A, 1B, 1D, 1E, 1G, 1I, 1K, 1L]
    private const THIRD_PLACE_SLOT_KEYS = ['1A', '1B', '1D', '1E', '1G', '1I', '1K', '1L'];

    // Match numbers that receive third-place teams (from bracket.json)
    private const THIRD_PLACE_MATCH_MAP = [
        '1A' => 79,
        '1B' => 85,
        '1D' => 82,
        '1E' => 75,
        '1G' => 81,
        '1I' => 78,
        '1K' => 88,
        '1L' => 80,
    ];

    /** Competitions whose format includes best-third-place qualifiers (legacy WC2026 only). */
    private const THIRD_PLACE_QUALIFIER_COMPETITIONS = ['WC2026'];

    /** @var array<string, array> competitionId → decoded bracket.json */
    private array $brackets = [];
    private ?array $thirdPlaceTable = null;

    /** @var array<string, \Illuminate\Support\Collection<int, CupTie>>  gameId:competitionId → ties */
    private array $completedTiesCache = [];

    /**
     * Get the first knockout round based on how many teams qualified.
     */
    public function getFirstKnockoutRound(int $qualifiedTeams): int
    {
        return match (true) {
            $qualifiedTeams > 16 => self::ROUND_OF_32,
            $qualifiedTeams > 8 => self::ROUND_OF_16,
            $qualifiedTeams > 4 => self::ROUND_QUARTER_FINALS,
            $qualifiedTeams > 2 => self::ROUND_SEMI_FINALS,
            default => self::ROUND_FINAL,
        };
    }

    /**
     * Get round config from schedule.json.
     *
     * Unlike the career-mode generators, this stays on Competition::season.
     * Tournament mode is retired (config('game.tournament_mode_enabled') is
     * off), and what remains is legacy: WC2026's row is pinned to the season
     * its data folder lives under (SeedWorldCupData::SEASON), and
     * app:seed-reference-data never touches it, so a base-season bump cannot
     * move it. There is no live career base season to follow here.
     */
    public function getRoundConfig(int $round, string $competitionId, ?string $gameSeason = null): PlayoffRoundConfig
    {
        $competition = Competition::find($competitionId);
        $rounds = LeagueFixtureGenerator::loadKnockoutRounds($competitionId, $competition->season, $gameSeason);

        foreach ($rounds as $config) {
            if ($config->round === $round) {
                return $config;
            }
        }

        throw new \RuntimeException("No knockout round config found for {$competitionId} round {$round}");
    }

    /**
     * Get the final round number from schedule.json.
     */
    public function getFinalRound(string $competitionId): int
    {
        $competition = Competition::find($competitionId);
        $rounds = LeagueFixtureGenerator::loadKnockoutRounds($competitionId, $competition->season);

        if (empty($rounds)) {
            return self::ROUND_FINAL;
        }

        return max(array_map(fn ($r) => $r->round, $rounds));
    }

    /**
     * Whether this competition's bracket defines a third-place match.
     * The Euros (WEURO) don't play one — the handler skips generating it.
     */
    public function hasThirdPlaceMatch(string $competitionId): bool
    {
        return count($this->loadBracket($competitionId)['third_place'] ?? []) > 0;
    }

    /**
     * Generate matchups for a knockout round.
     *
     * @return array<array{0: string, 1: string, 2: int|null}> Array of [homeTeamId, awayTeamId, bracketPosition]
     */
    public function generateMatchups(Game $game, string $competitionId, int $round): array
    {
        // Clear the completed ties cache so we pick up ties resolved in
        // the current request (e.g. batch-resolved R16 ties before generating QF).
        $this->completedTiesCache = [];

        if ($round === self::ROUND_OF_32) {
            return $this->generateRoundOf32($game, $competitionId);
        }

        return $this->generateFixedBracketRound($game, $competitionId, $round);
    }

    /**
     * Generate Round of 32 from group stage results using the fixed bracket.
     * WC2026-only path (48-team format with third-place qualifiers).
     */
    private function generateRoundOf32(Game $game, string $competitionId): array
    {
        $bracket = $this->loadBracket($competitionId);
        $r32Matches = $bracket['round_of_32'] ?? [];

        // Build group standings lookup: group_label + position → team_id
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->whereNotNull('group_label')
            ->get();

        $positionMap = []; // e.g., "1A" => team_id, "2B" => team_id
        foreach ($standings as $standing) {
            $key = $standing->position . $standing->group_label;
            $positionMap[$key] = $standing->team_id;
        }

        // Resolve third-place team assignments
        $thirdPlaceAssignment = $this->resolveThirdPlaceAssignment($game->id, $competitionId);

        $matchups = [];
        foreach ($r32Matches as $match) {
            $homeTeamId = $this->resolveR32Slot($match['home'], $positionMap, $thirdPlaceAssignment, $competitionId);
            $awayTeamId = $this->resolveR32Slot($match['away'], $positionMap, $thirdPlaceAssignment, $competitionId);

            if ($homeTeamId && $awayTeamId) {
                $matchups[] = [$homeTeamId, $awayTeamId, $match['match_number']];
            }
        }

        return $matchups;
    }

    /**
     * Resolve a R32 bracket slot reference to a team ID.
     *
     * Handles: "1A" (group winner), "2B" (runner-up), "3ABCDF" (third-place from eligible groups).
     */
    private function resolveR32Slot(string $slot, array $positionMap, array $thirdPlaceAssignment, string $competitionId): ?string
    {
        // Simple position + group: "1A", "2B", etc.
        if (preg_match('/^([12])([A-L])$/', $slot, $m)) {
            return $positionMap[$m[1] . $m[2]] ?? null;
        }

        // Third-place slot: "3ABCDF" — find which bracket match uses this slot label
        if (str_starts_with($slot, '3') && strlen($slot) > 2) {
            $bracket = $this->loadBracket($competitionId);
            foreach ($bracket['round_of_32'] as $entry) {
                if ($entry['home'] === $slot || $entry['away'] === $slot) {
                    return $thirdPlaceAssignment[$entry['match_number']] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Determine which 8 third-place teams qualify and assign them to bracket slots.
     *
     * @return array<int, string> match_number → team_id
     */
    private function resolveThirdPlaceAssignment(string $gameId, string $competitionId): array
    {
        // Get all 3rd-place teams ranked
        $thirdPlaceStandings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNotNull('group_label')
            ->where('position', 3)
            ->orderByDesc('points')
            ->orderByRaw('(goals_for - goals_against) DESC')
            ->orderByDesc('goals_for')
            ->orderBy('group_label')
            ->get();

        // Take best 8
        $qualifyingThird = $thirdPlaceStandings->take(8);

        // Build the qualifying groups key (sorted alphabetically)
        $qualifyingGroups = $qualifyingThird->pluck('group_label')->sort()->values()->implode('');

        // Lookup in the FIFA third-place assignment table
        $table = $this->loadThirdPlaceTable();
        $assignment = $table[$qualifyingGroups] ?? null;

        if (!$assignment) {
            throw new \RuntimeException("No third-place assignment found for qualifying groups: {$qualifyingGroups}");
        }

        // Build team lookup by group letter
        $teamByGroup = [];
        foreach ($qualifyingThird as $standing) {
            $teamByGroup[$standing->group_label] = $standing->team_id;
        }

        // Map slot indices to match numbers and team IDs
        // assignment = [1A_group, 1B_group, 1D_group, 1E_group, 1G_group, 1K_group, 1L_group]
        $result = [];
        foreach (self::THIRD_PLACE_SLOT_KEYS as $index => $slotKey) {
            $groupLetter = $assignment[$index];
            $matchNumber = self::THIRD_PLACE_MATCH_MAP[$slotKey];
            $result[$matchNumber] = $teamByGroup[$groupLetter] ?? null;
        }

        return $result;
    }

    /**
     * Generate later knockout rounds using the fixed bracket (W73, RU101, 1A, 2B...).
     *
     * Besides winner/loser references ("W73", "RU101"), the first knockout
     * round of the women's finals references group positions directly
     * ("1A", "2B") — resolved from the group standings here.
     */
    private function generateFixedBracketRound(Game $game, string $competitionId, int $round): array
    {
        // Third place and final are derived directly from SF results,
        // avoiding dependency on bracket_position which may be NULL.
        if ($round === self::ROUND_THIRD_PLACE || $round === self::ROUND_FINAL) {
            return $this->generateFromSemiFinals($game, $competitionId, $round);
        }

        $bracket = $this->loadBracket($competitionId);

        $roundKey = match ($round) {
            self::ROUND_OF_16 => 'round_of_16',
            self::ROUND_QUARTER_FINALS => 'quarter_finals',
            self::ROUND_SEMI_FINALS => 'semi_finals',
            default => throw new \RuntimeException("Unknown round: {$round}"),
        };

        // Group standings lookup for position slots ("1A", "2B") in the
        // first knockout round (women's finals).
        $positionMap = [];
        foreach (
            GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->whereNotNull('group_label')
                ->get() as $standing
        ) {
            $positionMap[$standing->position . $standing->group_label] = $standing->team_id;
        }

        $roundMatches = $bracket[$roundKey] ?? [];
        $matchups = [];

        foreach ($roundMatches as $match) {
            $homeTeamId = $this->resolveBracketReference($match['home'], $game->id, $competitionId, $positionMap);
            $awayTeamId = $this->resolveBracketReference($match['away'], $game->id, $competitionId, $positionMap);

            if ($homeTeamId && $awayTeamId) {
                $matchups[] = [$homeTeamId, $awayTeamId, $match['match_number']];
            }
        }

        return $matchups;
    }

    /**
     * Generate third-place or final matchup directly from semi-final results.
     *
     * Third place = SF losers, Final = SF winners. Competitions without a
     * third-place entry in their bracket (e.g. WEURO) return no matchups.
     */
    private function generateFromSemiFinals(Game $game, string $competitionId, int $round): array
    {
        $bracket = $this->loadBracket($competitionId);
        $roundKey = $round === self::ROUND_THIRD_PLACE ? 'third_place' : 'final';
        $roundMatches = $bracket[$roundKey] ?? [];

        if (empty($roundMatches)) {
            return [];
        }

        $sfTies = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', self::ROUND_SEMI_FINALS)
            ->where('completed', true)
            ->orderBy('bracket_position')
            ->orderBy('id')
            ->get();

        if ($sfTies->count() !== 2) {
            return [];
        }

        $matchNumber = $roundMatches[0]['match_number'];

        if ($round === self::ROUND_THIRD_PLACE) {
            $homeTeamId = $sfTies[0]->getLoserId();
            $awayTeamId = $sfTies[1]->getLoserId();
        } else {
            $homeTeamId = $sfTies[0]->winner_id;
            $awayTeamId = $sfTies[1]->winner_id;
        }

        if ($homeTeamId && $awayTeamId) {
            return [[$homeTeamId, $awayTeamId, $matchNumber]];
        }

        return [];
    }

    /**
     * Resolve a bracket reference to a team ID.
     *
     * Handles: "W73" (winner of match 73), "RU101" (loser of match 101),
     * "1A"/"2B" (group winner/runner-up, women's finals first KO round).
     */
    private function resolveBracketReference(string $ref, string $gameId, string $competitionId, array $positionMap = []): ?string
    {
        if (preg_match('/^W(\d+)$/', $ref, $m)) {
            $matchNumber = (int) $m[1];
            $tie = $this->findTieByBracketPosition($gameId, $competitionId, $matchNumber);

            return $tie?->winner_id;
        }

        if (preg_match('/^RU(\d+)$/', $ref, $m)) {
            $matchNumber = (int) $m[1];
            $tie = $this->findTieByBracketPosition($gameId, $competitionId, $matchNumber);

            return $tie?->getLoserId();
        }

        // Group-position slot, e.g. "1A" — first knockout round of the
        // women's finals.
        if (preg_match('/^([12])([A-H])$/', $ref, $m)) {
            return $positionMap[$m[1] . $m[2]] ?? null;
        }

        return null;
    }

    /**
     * Find a completed CupTie by its bracket_position (FIFA match number).
     *
     * Uses a per-game+competition cache to avoid N+1 queries when resolving
     * multiple bracket references in the same round.
     */
    private function findTieByBracketPosition(string $gameId, string $competitionId, int $matchNumber): ?CupTie
    {
        $cacheKey = $gameId . ':' . $competitionId;

        if (! isset($this->completedTiesCache[$cacheKey])) {
            $this->completedTiesCache[$cacheKey] = CupTie::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->where('completed', true)
                ->whereNotNull('bracket_position')
                ->get()
                ->keyBy('bracket_position');
        }

        return $this->completedTiesCache[$cacheKey]->get($matchNumber);
    }

    /**
     * Match numbers for each round in **bracket display order** — i.e. the order in which
     * slots should appear top-to-bottom so that paired ties (whose winners meet in the next
     * round) sit adjacent. Derived by walking bracket.json from the final back to the
     * first knockout round.
     *
     * Without this, sorting purely by `bracket_position` produces visually misaligned pairs
     * (e.g. match 73 pairs with match 75 in R16, not match 74).
     *
     * @return array<int, array<int>>  round_number => [match_number, ...] in display order
     */
    public function getDisplayOrderPerRound(string $competitionId): array
    {
        $bracket = $this->loadBracket($competitionId);

        $finalMatches = array_map(fn ($m) => $m['match_number'], $bracket['final'] ?? []);

        $orderByRound = [self::ROUND_FINAL => $finalMatches];

        $rounds = [
            self::ROUND_SEMI_FINALS => 'semi_finals',
            self::ROUND_QUARTER_FINALS => 'quarter_finals',
            self::ROUND_OF_16 => 'round_of_16',
            self::ROUND_OF_32 => 'round_of_32',
        ];

        $currentRound = self::ROUND_FINAL;
        foreach ($rounds as $roundNumber => $roundKey) {
            $parentOrder = $orderByRound[$currentRound];
            $parentLookup = [];
            foreach ($bracket[$this->roundKey($currentRound)] ?? [] as $match) {
                $parentLookup[$match['match_number']] = $match;
            }

            $childOrder = [];
            foreach ($parentOrder as $parentMatchNumber) {
                $parent = $parentLookup[$parentMatchNumber] ?? null;
                if (! $parent) {
                    continue;
                }
                foreach ([$parent['home'], $parent['away']] as $ref) {
                    if (preg_match('/^W(\d+)$/', $ref, $m)) {
                        $childOrder[] = (int) $m[1];
                    }
                }
            }

            $orderByRound[$roundNumber] = $childOrder;
            $currentRound = $roundNumber;
        }

        // Third-place is rendered out-of-band, but expose its match number(s) for completeness.
        $orderByRound[self::ROUND_THIRD_PLACE] = array_map(
            fn ($m) => $m['match_number'],
            $bracket['third_place'] ?? []
        );

        return $orderByRound;
    }

    private function roundKey(int $round): string
    {
        return match ($round) {
            self::ROUND_OF_32 => 'round_of_32',
            self::ROUND_OF_16 => 'round_of_16',
            self::ROUND_QUARTER_FINALS => 'quarter_finals',
            self::ROUND_SEMI_FINALS => 'semi_finals',
            self::ROUND_THIRD_PLACE => 'third_place',
            self::ROUND_FINAL => 'final',
        };
    }

    /**
     * Slot count per round, derived from bracket.json. Used by the bracket UI to render
     * empty placeholders for rounds whose CupTie rows haven't been generated yet.
     *
     * @return array<int, int>  round_number => slot count
     */
    public function getSlotsPerRound(string $competitionId): array
    {
        $bracket = $this->loadBracket($competitionId);

        return [
            self::ROUND_OF_32 => count($bracket['round_of_32'] ?? []),
            self::ROUND_OF_16 => count($bracket['round_of_16'] ?? []),
            self::ROUND_QUARTER_FINALS => count($bracket['quarter_finals'] ?? []),
            self::ROUND_SEMI_FINALS => count($bracket['semi_finals'] ?? []),
            self::ROUND_THIRD_PLACE => count($bracket['third_place'] ?? []),
            self::ROUND_FINAL => count($bracket['final'] ?? []),
        ];
    }

    /**
     * Get teams that qualified from the group stage.
     *
     * WC2026 (legacy 48-team format): top 2 per group + best 8
     * third-place teams. Women's finals (WWCU27, WEURO): top 2 per
     * group only.
     *
     * @return array<string> Team IDs
     */
    public function getQualifiedTeams(string $gameId, string $competitionId): array
    {
        // Top 2 from each group
        $top2 = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNotNull('group_label')
            ->where('position', '<=', 2)
            ->pluck('team_id')
            ->toArray();

        if (!in_array($competitionId, self::THIRD_PLACE_QUALIFIER_COMPETITIONS, true)) {
            return $top2;
        }

        // Best 8 third-place teams (WC2026 legacy format only)
        $thirdPlace = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNotNull('group_label')
            ->where('position', 3)
            ->orderByDesc('points')
            ->orderByRaw('(goals_for - goals_against) DESC')
            ->orderByDesc('goals_for')
            ->orderBy('group_label')
            ->take(8)
            ->pluck('team_id')
            ->toArray();

        return array_merge($top2, $thirdPlace);
    }

    /**
     * Load (and cache) the knockout bracket for a competition.
     *
     * WC2026 keeps its legacy data/2025 path; every other competition
     * reads data/2026/{id}/bracket.json.
     */
    private function loadBracket(string $competitionId): array
    {
        if (!isset($this->brackets[$competitionId])) {
            $path = $competitionId === 'WC2026'
                ? base_path('data/2025/WC2026/bracket.json')
                : base_path("data/2026/{$competitionId}/bracket.json");

            if (!file_exists($path)) {
                throw new \RuntimeException("Bracket file not found for competition {$competitionId}: {$path}");
            }

            $this->brackets[$competitionId] = json_decode(file_get_contents($path), true);
        }

        return $this->brackets[$competitionId];
    }

    private function loadThirdPlaceTable(): array
    {
        if ($this->thirdPlaceTable === null) {
            $path = base_path('data/2025/WC2026/third_place_table.json');
            $this->thirdPlaceTable = json_decode(file_get_contents($path), true);
        }

        return $this->thirdPlaceTable;
    }
}
