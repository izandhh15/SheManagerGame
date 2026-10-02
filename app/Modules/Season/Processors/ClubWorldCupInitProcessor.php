<?php

namespace App\Modules\Season\Processors;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Draws the women's Club World Cup groups and generates the group-stage
 * fixtures at season setup.
 *
 * Runs after ContinentalAndCupInitProcessor (106): the 32 qualified clubs
 * (written by ClubWorldCupQualificationProcessor at season close) are drawn
 * into 8 groups of 4 — A to H — seeded by the continental ranking (squad
 * market value, strongest spread across groups) with the real-world
 * confederation rule: max 2 UEFA and max 1 CONMEBOL / 1 CONCACAF per group.
 *
 * The group stage is played in early July, opening the new season; the
 * knockout bracket (R16 → Final) is then generated progressively by
 * GroupStageCupHandler from data/2026/CWC/bracket.json, exactly like the
 * other group_stage_cup tournaments.
 */
class ClubWorldCupInitProcessor implements SeasonProcessor
{
    public const COMPETITION_ID = 'CWC';

    /** Group-stage matchday offsets from the group dates in schedule.json. */
    private const GROUP_LABELS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    public function priority(): int
    {
        return 107;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if (!$game->isCareerMode()) {
            return $data;
        }

        $entries = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', self::COMPETITION_ID)
            ->pluck('team_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (count($entries) !== ClubWorldCupQualificationProcessor::TEAM_COUNT) {
            return $data;
        }

        // Idempotency: the draw already happened.
        if (GameMatch::where('game_id', $game->id)
            ->where('competition_id', self::COMPETITION_ID)
            ->exists()) {
            return $data;
        }

        $groups = $this->drawGroups($game, $entries);

        $this->createGroupFixtures($game, $groups);
        $this->createGroupStandings($game, $groups);

        Log::info('[ClubWorldCup] drew ' . count($groups) . " groups for game {$game->id}");

        return $data;
    }

    /**
     * Draw 8 groups of 4, seeded by squad market value (strongest spread
     * across groups via snake dealing) with max 2 UEFA and max 1
     * CONMEBOL / 1 CONCACAF per group.
     *
     * @param string[] $teamIds
     * @return array<string, string[]> group label → team ids
     */
    private function drawGroups(Game $game, array $teamIds): array
    {
        $squadValues = GamePlayer::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->selectRaw('team_id, SUM(market_value_cents) AS squad_value')
            ->groupBy('team_id')
            ->pluck('squad_value', 'team_id');

        $countries = Team::whereIn('id', $teamIds)->pluck('country', 'id');

        $byConfederation = ['UEFA' => [], 'CONMEBOL' => [], 'CONCACAF' => []];
        foreach ($teamIds as $teamId) {
            $confederation = $this->confederationFor($countries[$teamId] ?? null) ?? 'UEFA';
            $byConfederation[$confederation][] = $teamId;
        }

        // Strongest first inside each confederation.
        foreach ($byConfederation as &$list) {
            usort($list, fn ($a, $b) => ($squadValues[$b] ?? 0) <=> ($squadValues[$a] ?? 0));
        }
        unset($list);

        $groups = array_fill_keys(self::GROUP_LABELS, []);

        // Snake-deal each confederation across the groups: with the
        // 16/8/8 slot split every group ends up with exactly 2 UEFA,
        // 1 CONMEBOL and 1 CONCACAF, strength spread evenly.
        foreach ($byConfederation as $list) {
            foreach ($list as $i => $teamId) {
                $pass = intdiv($i, 8);
                $pos = $i % 8;
                $groupIndex = ($pass % 2 === 0) ? $pos : (7 - $pos);
                $groups[self::GROUP_LABELS[$groupIndex]][] = $teamId;
            }
        }

        // Shuffle inside each group so fixture order isn't seeded order.
        foreach ($groups as &$group) {
            shuffle($group);
        }
        unset($group);

        return $groups;
    }

    private function confederationFor(?string $country): ?string
    {
        if ($country === null || $country === '' || $country === 'XX') {
            return null;
        }
        if (in_array($country, ['AR', 'BR'], true)) {
            return 'CONMEBOL';
        }
        if (in_array($country, ['MX', 'US'], true)) {
            return 'CONCACAF';
        }

        return 'UEFA';
    }

    /**
     * Single round-robin: 3 matchdays per group, dated from the
     * competition's schedule.json (year-adjusted to the game season).
     */
    private function createGroupFixtures(Game $game, array $groups): void
    {
        $dates = $this->groupStageDates($game);

        // Pairings for 4 teams [0,1,2,3], one leg each.
        $rounds = [
            [[0, 3], [1, 2]],
            [[0, 2], [3, 1]],
            [[0, 1], [2, 3]],
        ];

        $rows = [];
        foreach ($groups as $label => $teamIds) {
            foreach ($rounds as $roundIndex => $pairs) {
                foreach ($pairs as [$a, $b]) {
                    if (!isset($teamIds[$a], $teamIds[$b])) {
                        continue;
                    }
                    $rows[] = [
                        'id' => Str::uuid()->toString(),
                        'game_id' => $game->id,
                        'competition_id' => self::COMPETITION_ID,
                        'round_number' => $roundIndex + 1,
                        'round_name' => __('game.group_stage') . ' ' . $label . ' · ' . __('game.matchday') . ' ' . ($roundIndex + 1),
                        'home_team_id' => $teamIds[$a],
                        'away_team_id' => $teamIds[$b],
                        'scheduled_date' => $dates[$roundIndex],
                        'played' => false,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            GameMatch::insert($chunk);
        }
    }

    private function createGroupStandings(Game $game, array $groups): void
    {
        $rows = [];
        foreach ($groups as $label => $teamIds) {
            $position = 1;
            foreach ($teamIds as $teamId) {
                $rows[] = [
                    'game_id' => $game->id,
                    'competition_id' => self::COMPETITION_ID,
                    'group_label' => $label,
                    'team_id' => $teamId,
                    'position' => $position,
                    'prev_position' => null,
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'points' => 0,
                ];
                $position++;
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            GameStanding::insert($chunk);
        }
    }

    /**
     * Group-stage dates from data/2026/CWC/schedule.json, shifted to the
     * game's season (the knockout dates in the same file are consumed the
     * same way by LeagueFixtureGenerator::loadKnockoutRounds).
     *
     * @return string[] Y-m-d dates for matchdays 1-3
     */
    private function groupStageDates(Game $game): array
    {
        $seasonYear = (int) $game->season;
        $fallback = [
            Carbon::create($seasonYear, 7, 4)->toDateString(),
            Carbon::create($seasonYear, 7, 7)->toDateString(),
            Carbon::create($seasonYear, 7, 10)->toDateString(),
        ];

        $path = base_path('data/2026/CWC/schedule.json');
        if (!file_exists($path)) {
            return $fallback;
        }

        $leagueDates = array_column(json_decode(file_get_contents($path), true)['league'] ?? [], 'date');
        if (count($leagueDates) < 3) {
            return $fallback;
        }

        $yearDiff = $seasonYear - 2026;

        return array_map(
            fn ($date) => Carbon::parse($date)->addYears($yearDiff)->toDateString(),
            array_slice($leagueDates, 0, 3),
        );
    }
}
