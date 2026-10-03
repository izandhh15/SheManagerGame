<?php

namespace App\Modules\Competition\Playoffs;

use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\TeamReputation;
use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Modules\Competition\Services\ReserveTeamFilter;
use App\Modules\Finance\Services\SeasonSimulationService;

/**
 * Promotion playoff from Segunda Federación (ESP3A/B/C) to Primera Federación.
 *
 * Format (2025/26 Segunda Federación rules, as requested):
 * - The 3 group champions are promoted directly (handled by
 *   CountryPromotionRelegationPlanner via direct_count).
 * - The playoff bracket is formed by the 3 runners-up plus the best
 *   third-placed team across the three groups (by points).
 * - Semifinals (1v4, 2v3) and a final, all two-legged; the single winner
 *   is promoted.
 *
 * Reserve teams whose parent club plays in Primera Federación are excluded
 * from the bracket (their slot passes to the next eligible team in the
 * same group).
 */
class SegundaFederacionPlayoffGenerator implements PlayoffGenerator
{
    private const GROUP_IDS = ['ESP3A', 'ESP3B', 'ESP3C'];

    private const TOP_DIVISION = 'ESP2';

    private const PLAYOFF_ID = 'ESP3PO';

    public function __construct(
        private readonly string $competitionId = self::PLAYOFF_ID,
        private readonly int $directCount = 1,
        private readonly int $playoffCount = 1,
        private readonly int $triggerMatchday = 26,
    ) {
    }

    public function getCompetitionId(): string
    {
        return $this->competitionId;
    }

    public function getQualifyingPositions(): array
    {
        return [2, 3];
    }

    public function getDirectPromotionPositions(): array
    {
        return [1];
    }

    public function getTriggerMatchday(): int
    {
        return $this->triggerMatchday;
    }

    public function getTotalRounds(): int
    {
        return 2;
    }

    public function getRoundConfig(int $round, Game $game): PlayoffRoundConfig
    {
        $rounds = LeagueFixtureGenerator::loadKnockoutRounds(
            $this->competitionId,
            $game->base_season,
            $game->season,
        );

        foreach ($rounds as $config) {
            if ($config->round === $round) {
                return $config;
            }
        }

        throw new \RuntimeException("No knockout round config found for {$this->competitionId} round {$round}");
    }

    public function generateMatchups(Game $game, int $round): array
    {
        return match ($round) {
            1 => $this->generateSemifinalMatchups($game),
            2 => $this->generateFinalMatchup($game),
            default => throw new \InvalidArgumentException("Invalid playoff round: {$round}"),
        };
    }

    public function isComplete(Game $game): bool
    {
        $finalTie = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->where('round_number', $this->getTotalRounds())
            ->first();

        return $finalTie !== null && $finalTie->completed === true && $finalTie->winner_id !== null;
    }

    public function state(Game $game): PlayoffState
    {
        if ($this->isComplete($game)) {
            return PlayoffState::Completed;
        }

        $anyTieExists = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->exists();

        return $anyTieExists ? PlayoffState::InProgress : PlayoffState::NotStarted;
    }

    /**
     * Semifinals: seed 1 vs seed 4, seed 2 vs seed 3 (two legs).
     * The lower-seeded team hosts the first leg.
     */
    private function generateSemifinalMatchups(Game $game): array
    {
        $seeds = $this->computeSeeds($game);

        if (count($seeds) !== 4) {
            throw new \RuntimeException(
                'Not enough eligible teams for the Segunda Federación playoff: need 4, found '
                . count($seeds) . ' (3 runners-up + best third, after reserve filtering).'
            );
        }

        $this->populateCompetitionEntries($game, $seeds);

        // Seed 1 vs seed 4, seed 2 vs seed 3; lower seed hosts leg 1.
        return [
            [$seeds[3], $seeds[0], 1],
            [$seeds[2], $seeds[1], 2],
        ];
    }

    /**
     * Final: winners of the two semifinals (two legs).
     *
     * The winner with the worse seed (larger seed number) hosts the first
     * leg, so the better-seeded winner gets the deciding second leg at
     * home. Seeds are recomputed deterministically from the regular-season
     * tables (unchanged since the playoff started).
     */
    private function generateFinalMatchup(Game $game): array
    {
        $semifinalWinners = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->where('round_number', 1)
            ->where('completed', true)
            ->orderBy('bracket_position')
            ->pluck('winner_id')
            ->toArray();

        if (count($semifinalWinners) !== 2) {
            throw new \RuntimeException('Cannot generate Segunda Federación playoff final: semifinals not complete');
        }

        $seeds = $this->computeSeeds($game);
        $seedOf = array_flip($seeds);

        $seedA = $seedOf[$semifinalWinners[0]] ?? PHP_INT_MAX;
        $seedB = $seedOf[$semifinalWinners[1]] ?? PHP_INT_MAX;

        // Worse seed (larger number) hosts leg 1.
        if ($seedA > $seedB) {
            return [[$semifinalWinners[0], $semifinalWinners[1]]];
        }

        return [[$semifinalWinners[1], $semifinalWinners[0]]];
    }

    /**
     * Compute the 4 playoff seeds: the 3 runners-up ordered by points
     * (goal difference, then reputation as tiebreakers), plus the best
     * third-placed team across the three groups as seed 4.
     *
     * @return array<int, string> Team UUIDs ordered seed 1..4.
     */
    private function computeSeeds(Game $game): array
    {
        $runnersUp = [];
        $thirds = [];

        foreach (self::GROUP_IDS as $groupId) {
            $eligible = $this->orderedEligibleTeams($game, $groupId);

            if (count($eligible) < 2) {
                continue;
            }

            $runnersUp[] = $eligible[0];
            $thirds[] = $eligible[1];
        }

        if (count($runnersUp) !== 3 || count($thirds) !== 3) {
            return [];
        }

        $byScore = fn (array $a, array $b): int =>
            [$b['points'], $b['goalDiff'], $b['repPoints']]
            <=> [$a['points'], $a['goalDiff'], $a['repPoints']];

        usort($runnersUp, $byScore);
        usort($thirds, $byScore);

        return [
            $runnersUp[0]['teamId'],
            $runnersUp[1]['teamId'],
            $runnersUp[2]['teamId'],
            $thirds[0]['teamId'],
        ];
    }

    /**
     * Ordered list of teams eligible for the playoff from one group:
     * positions 2..N (the champion is promoted directly), skipping reserve
     * teams whose parent club plays in Primera Federación.
     *
     * Each entry carries the scoring fields used to rank runners-up and to
     * pick the best third: real points/goal difference when the group has
     * real standings, or a reputation-based estimate for simulated groups.
     *
     * @return array<int, array{teamId: string, points: int, goalDiff: int, repPoints: int}>
     */
    private function orderedEligibleTeams(Game $game, string $groupId): array
    {
        $reserveFilter = app(ReserveTeamFilter::class);
        $esp2TeamIds = $reserveFilter->getTopDivisionTeamIds($game, $groupId);

        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $groupId)
            ->orderBy('position')
            ->get();

        $teamIds = $standings->pluck('team_id')->all();
        $real = true;

        if ($standings->isEmpty()) {
            $teamIds = $this->orderedTeamsFromSimulation($game, $groupId);
            $real = false;
        }

        if (empty($teamIds)) {
            return [];
        }

        $parentMap = $reserveFilter->loadParentTeamIds($teamIds);
        $levels = TeamReputation::resolveLevels($game->id, $teamIds);

        $eligible = [];
        $position = 0;

        foreach ($teamIds as $teamId) {
            $position++;

            // Skip the champion (direct promotion).
            if ($position < 2) {
                continue;
            }

            if ($reserveFilter->isBlockedReserveTeam($teamId, $esp2TeamIds, $parentMap)) {
                continue;
            }

            $standing = $real ? $standings->firstWhere('team_id', $teamId) : null;
            $repPoints = TeamReputation::pointsForTier($levels->get($teamId) ?? 'local');

            if ($standing) {
                $points = (int) $standing->points;
                $goalDiff = (int) $standing->goal_difference;
            } else {
                // Simulated group: estimate points from reputation. A 14-team
                // group plays 26 matchdays; thirds usually land around 45-58
                // points, so map local..elite (0..400 rep points) to 30..60.
                $points = 30 + (int) round($repPoints / 400 * 30);
                $goalDiff = 0;
            }

            $eligible[] = [
                'teamId' => $teamId,
                'points' => $points,
                'goalDiff' => $goalDiff,
                'repPoints' => $repPoints,
            ];
        }

        return $eligible;
    }

    /**
     * Fallback ordering for groups without real standings: lazily simulate
     * the group season (same simulated-season fallback pattern used
     * across group playoff generators).
     *
     * @return array<int, string> Team UUIDs ordered 1st..last.
     */
    private function orderedTeamsFromSimulation(Game $game, string $groupId): array
    {
        $existing = SimulatedSeason::where('game_id', $game->id)
            ->where('competition_id', $groupId)
            ->where('season', $game->season)
            ->first();

        if ($existing && !empty($existing->results)) {
            return array_values($existing->results);
        }

        $competition = \App\Models\Competition::find($groupId);
        if ($competition === null) {
            return [];
        }

        $simulated = app(SeasonSimulationService::class)->simulateLeague($game, $competition);

        return array_values($simulated->results ?? []);
    }

    /**
     * Populate ESP3PO competition entries so the bracket teams appear in the
     * competition's participant list (and the competition page renders).
     */
    private function populateCompetitionEntries(Game $game, array $teamIds): void
    {
        foreach ($teamIds as $teamId) {
            \App\Models\CompetitionEntry::firstOrCreate(
                [
                    'game_id' => $game->id,
                    'competition_id' => $this->competitionId,
                    'team_id' => $teamId,
                ],
                ['joined_at' => now()]
            );
        }
    }
}
