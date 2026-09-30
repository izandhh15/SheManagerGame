<?php

namespace App\Modules\Competition\Services;

use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use Carbon\Carbon;

/**
 * Generates round-robin league fixtures using the circle method.
 *
 * For N teams (must be even), generates N-1 rounds for the first half of the season,
 * then mirrors them (swapping home/away) for the second half.
 *
 * Input: team IDs + matchday schedule (dates per round).
 * Output: flat array of fixtures matching SwissDrawService format.
 */
class LeagueFixtureGenerator
{
    /**
     * Load matchday calendar from a competition's schedule.json file.
     *
     * @param  string  $competitionId  e.g. 'ESP1', 'ESP2'
     * @param  string  $season  e.g. '2025'
     * @return array<array{round: int, date: string}>  Dates in YYYY-MM-DD format
     */
    public static function loadMatchdays(string $competitionId, string $season): array
    {
        $path = base_path("data/{$season}/{$competitionId}/schedule.json");

        if (!file_exists($path)) {
            throw new \RuntimeException("Schedule file not found: {$path}");
        }

        $data = json_decode(file_get_contents($path), true);

        return $data['league'] ?? [];
    }

    /**
     * Adjust matchday dates by a year offset.
     * Used for generating fixtures in subsequent seasons.
     *
     * @param  array<array{round: int, date: string}>  $matchdays  Dates in YYYY-MM-DD format
     * @param  int  $yearOffset  Number of years to add (e.g. 1 for next season)
     * @return array<array{round: int, date: string}>
     */
    public static function adjustMatchdayYears(array $matchdays, int $yearOffset): array
    {
        return array_map(function ($md) use ($yearOffset) {
            $date = Carbon::parse($md['date'])->addYears($yearOffset);

            $adjusted = [
                'round' => $md['round'],
                'date' => $date->format('Y-m-d'),
            ];

            // Preserve explicit matchups (real calendars); the caller
            // decides whether they apply (base season only).
            if (isset($md['matches'])) {
                $adjusted['matches'] = $md['matches'];
            }

            return $adjusted;
        }, $matchdays);
    }
    /**
     * Load knockout rounds from a competition's schedule.json file.
     *
     * Dates in schedule.json are stored for the base season (e.g. 2025).
     * Pass $gameSeason to automatically adjust dates for later seasons.
     *
     * @param  string  $competitionId  e.g. 'ESPCUP', 'UCL', 'ESP2'
     * @param  string  $baseSeason  e.g. '2025' (base season from Competition::season)
     * @param  string|null  $gameSeason  e.g. '2027' (current game season, for year adjustment)
     * @return PlayoffRoundConfig[]
     */
    public static function loadKnockoutRounds(string $competitionId, string $baseSeason, ?string $gameSeason = null): array
    {
        $path = base_path("data/{$baseSeason}/{$competitionId}/schedule.json");

        if (!file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);
        $rounds = $data['knockout'] ?? [];

        $configs = array_map(function ($round) {
            $hasTwoLegs = isset($round['second_leg_date']);
            $firstLegDate = $hasTwoLegs ? $round['first_leg_date'] : ($round['date'] ?? null);

            return new PlayoffRoundConfig(
                round: $round['round'],
                name: $round['name'],
                twoLegged: $hasTwoLegs,
                firstLegDate: Carbon::parse($firstLegDate),
                secondLegDate: $hasTwoLegs ? Carbon::parse($round['second_leg_date']) : null,
            );
        }, $rounds);

        if ($gameSeason !== null) {
            $yearDiff = (int) $gameSeason - (int) $baseSeason;
            if ($yearDiff !== 0) {
                $configs = self::adjustKnockoutYears($configs, $yearDiff);
            }
        }

        return $configs;
    }

    /**
     * The round number of a knockout competition's final — the last round in
     * its schedule.json — or null when the competition has no knockout
     * schedule. Processors that need "the cup final" (supercup and UEFA
     * cup-winner qualification) read it from here instead of repeating the
     * round number in config.
     */
    public static function finalKnockoutRound(string $competitionId, string $baseSeason): ?int
    {
        $rounds = self::loadKnockoutRounds($competitionId, $baseSeason);
        if ($rounds === []) {
            return null;
        }

        return max(array_map(fn (PlayoffRoundConfig $round) => $round->round, $rounds));
    }

    /**
     * Adjust knockout round dates by a year offset.
     *
     * @param  PlayoffRoundConfig[]  $rounds
     * @param  int  $yearOffset
     * @return PlayoffRoundConfig[]
     */
    public static function adjustKnockoutYears(array $rounds, int $yearOffset): array
    {
        return array_map(function (PlayoffRoundConfig $round) use ($yearOffset) {
            return new PlayoffRoundConfig(
                round: $round->round,
                name: $round->name,
                twoLegged: $round->twoLegged,
                firstLegDate: $round->firstLegDate->copy()->addYears($yearOffset),
                secondLegDate: $round->secondLegDate?->copy()->addYears($yearOffset),
            );
        }, $rounds);
    }

    /**
     * Sentinel team inserted internally when the league has an odd team
     * count (e.g. Seconde Ligue's 11 teams). Fixtures drawn against it are
     * bye weeks and are filtered out of the returned list. A UUID can never
     * equal this value, so it cannot collide with a real team.
     */
    private const BYE_TEAM = '__BYE__';

    /**
     * Generate a full double round-robin schedule.
     *
     * Even counts need 2*(N-1) matchdays; odd counts need 2*N matchdays
     * (each round one team rests).
     *
     * @param  array<string>  $teamIds  Team IDs (even count ≥ 4, or odd count ≥ 5)
     * Generates round-robin league fixtures using the circle method.
     *
     * For N teams (must be even), generates N-1 rounds for the first half of the season,
     * then mirrors them (swapping home/away) for the second half.
     *
     * When a matchday defines explicit 'matches' (pairs of transfermarktIds)
     * and $tmIdToTeamId is provided, those real-calendar pairings are used
     * instead of the circle method. This is intended for the base season
     * only — later seasons (different teams via promotion/relegation) fall
     * back to the circle method.
     *
     * Input: team IDs + matchday schedule (dates per round).
     * Output: flat array of fixtures matching SwissDrawService format.
     */
    public function generate(array $teamIds, array $matchdays, ?array $tmIdToTeamId = null): array
    {
        $hasExplicit = $tmIdToTeamId !== null
            && collect($matchdays)->contains(fn ($md) => !empty($md['matches']));

        if ($hasExplicit) {
            return $this->generateExplicit($teamIds, $matchdays, $tmIdToTeamId);
        }

        return $this->generateCircle($teamIds, $matchdays);
    }

    /**
     * Build fixtures from explicit real-calendar matchups.
     *
     * Each matchday's 'matches' is a list of [homeTmId, awayTmId] pairs
     * using transfermarktIds. Pairs referencing teams not in this game
     * (e.g. stale data) are skipped. Every round must define matches —
     * a partial real calendar is a data error.
     *
     * @param  array<string>  $teamIds  Team UUIDs in this game/league
     * @param  array<array{round: int, date: string, matches?: array<array{int, int}>}>  $matchdays
     * @param  array<int, string>  $tmIdToTeamId  transfermarktId => team UUID
     * @return array<array{matchday: int, date: string, homeTeamId: string, awayTeamId: string}>
     */
    private function generateExplicit(array $teamIds, array $matchdays, array $tmIdToTeamId): array
    {
        $teamSet = array_flip($teamIds);
        $fixtures = [];

        foreach ($matchdays as $md) {
            $matches = $md['matches'] ?? null;

            if (empty($matches)) {
                throw new \InvalidArgumentException(
                    "Round {$md['round']} has no explicit matches but other rounds do — " .
                    "real calendars must define matchups for every round or none."
                );
            }

            foreach ($matches as $pair) {
                [$homeTm, $awayTm] = $pair;
                $homeId = $tmIdToTeamId[$homeTm] ?? null;
                $awayId = $tmIdToTeamId[$awayTm] ?? null;

                if ($homeId === null || $awayId === null) {
                    continue;
                }

                if (!isset($teamSet[$homeId]) || !isset($teamSet[$awayId])) {
                    continue;
                }

                $fixtures[] = [
                    'matchday' => $md['round'],
                    'date' => $md['date'],
                    'homeTeamId' => $homeId,
                    'awayTeamId' => $awayId,
                ];
            }
        }

        return $fixtures;
    }

    /**
     * @param  array<string>  $teamIds
     * @param  array<array{round: int, date: string}>  $matchdays  Schedule with round numbers and dates (YYYY-MM-DD)
     * @return array<array{matchday: int, date: string, homeTeamId: string, awayTeamId: string}>
     */
    private function generateCircle(array $teamIds, array $matchdays): array
    {
        $teamCount = count($teamIds);
        $isOdd = $teamCount % 2 !== 0;

        if ($teamCount < 4 || ($isOdd && $teamCount < 5)) {
            throw new \InvalidArgumentException(
                "Team count must be even and at least 4 (or odd and at least 5), got {$teamCount}"
            );
        }

        $halfSeason = $isOdd ? $teamCount : $teamCount - 1;
        $expectedMatchdays = $halfSeason * 2;

        if (count($matchdays) !== $expectedMatchdays) {
            throw new \InvalidArgumentException(
                "Expected {$expectedMatchdays} matchdays for {$teamCount} teams, got " . count($matchdays)
            );
        }

        // Shuffle team order so the schedule is different each time
        $teams = $teamIds;
        shuffle($teams);

        if ($isOdd) {
            $teams[] = self::BYE_TEAM;
        }

        $firstHalf = $this->generateFirstHalf($teams);

        $fixtures = $this->buildFixtures($firstHalf, $matchdays, $teams);

        if ($isOdd) {
            // Drop bye-week fixtures; every real team rests exactly once
            // per half-season.
            $fixtures = array_values(array_filter(
                $fixtures,
                fn (array $f) => $f['homeTeamId'] !== self::BYE_TEAM
                    && $f['awayTeamId'] !== self::BYE_TEAM
            ));
        }

        return $fixtures;
    }

    /**
     * Generate first half pairings using the circle method.
     *
     * Fixes team[0] in place and rotates the rest clockwise.
     * Returns array indexed by round (0-based), each containing
     * pairs of [homeIndex, awayIndex] into the $teams array.
     *
     * @param  array<string>  $teams
     * @return array<int, array<array{int, int}>>
     */
    private function generateFirstHalf(array $teams): array
    {
        $n = count($teams);
        $halfN = $n / 2;
        $rounds = $n - 1;

        // Positions 0..n-2 rotate; position 0 is fixed.
        // We build a "rotating" array of indices 1..n-1
        $rotating = range(1, $n - 1);

        $schedule = [];

        for ($round = 0; $round < $rounds; $round++) {
            $pairs = [];

            // First pair: fixed team (index 0) vs first in rotation
            // Alternate home/away for the fixed team to balance
            if ($round % 2 === 0) {
                $pairs[] = [0, $rotating[0]];
            } else {
                $pairs[] = [$rotating[0], 0];
            }

            // Remaining pairs: mirror positions from rotation array
            // Alternate home/away by pair position to minimize consecutive
            // same-venue games (achieves theoretical minimum of n-2 breaks)
            for ($i = 1; $i < $halfN; $i++) {
                if ($i % 2 === 1) {
                    $pairs[] = [$rotating[$i], $rotating[$n - 1 - $i]];
                } else {
                    $pairs[] = [$rotating[$n - 1 - $i], $rotating[$i]];
                }
            }

            $schedule[] = $pairs;

            // Rotate: last element moves to front
            $last = array_pop($rotating);
            array_unshift($rotating, $last);
        }

        return $schedule;
    }

    /**
     * Build the flat fixture array from first-half pairings.
     *
     * Second half mirrors first half with home/away swapped.
     *
     * @param  array<int, array<array{int, int}>>  $firstHalf
     * @param  array<array{round: int, date: string}>  $matchdays
     * @param  array<string>  $teams
     * @return array<array{matchday: int, date: string, homeTeamId: string, awayTeamId: string}>
     */
    private function buildFixtures(array $firstHalf, array $matchdays, array $teams): array
    {
        $fixtures = [];
        $halfSeason = count($firstHalf);

        // Index matchdays by round number for lookup
        $matchdayMap = [];
        foreach ($matchdays as $md) {
            $matchdayMap[$md['round']] = $md['date'];
        }

        // First half of the season
        for ($round = 0; $round < $halfSeason; $round++) {
            $matchday = $round + 1;
            $date = $matchdayMap[$matchday];

            foreach ($firstHalf[$round] as [$homeIdx, $awayIdx]) {
                $fixtures[] = [
                    'matchday' => $matchday,
                    'date' => $date,
                    'homeTeamId' => $teams[$homeIdx],
                    'awayTeamId' => $teams[$awayIdx],
                ];
            }
        }

        // Second half: swap home/away
        for ($round = 0; $round < $halfSeason; $round++) {
            $matchday = $halfSeason + $round + 1;
            $date = $matchdayMap[$matchday];

            foreach ($firstHalf[$round] as [$homeIdx, $awayIdx]) {
                $fixtures[] = [
                    'matchday' => $matchday,
                    'date' => $date,
                    'homeTeamId' => $teams[$awayIdx],
                    'awayTeamId' => $teams[$homeIdx],
                ];
            }
        }

        return $fixtures;
    }
}
