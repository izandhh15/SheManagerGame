<?php

namespace App\Modules\Competition\Playoffs;

use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Modules\Season\Services\SeasonSimulationService;

/**
 * WSL relegation playoff: 13th of ENG1 (WSL) vs 2nd of ENG2 (WSL2).
 *
 * Single match, hosted by the ENG2 team. Winner takes the WSL spot for
 * next season: if the ENG2 team wins they are promoted (and the ENG1 team
 * is relegated); if the ENG1 team wins the status quo holds.
 *
 * The generator is registered under BOTH ENG1 and ENG2 (via the rule's
 * playoff_trigger_divisions) so it fires when the player's league ends.
 * The player's league uses real standings; the other league — which the
 * game never simulates (a game only has fixtures for the player's own
 * competition) — is lazily simulated to determine its participant.
 *
 * Perspective-aware: getQualifyingPositions()/getDirectPromotionPositions()
 * answer for the division the generator was resolved for (via
 * PlayoffGeneratorFactory::forCompetition), so ENG1's 13th shows the
 * playoff spot and ENG2's 1st/2nd show direct promotion / playoff.
 */
class WSLRelegationPlayoffGenerator implements PlayoffGenerator
{
    private const PLAYOFF_ID = 'ENGPO';
    private const ENG1_ID = 'ENG1';
    private const ENG2_ID = 'ENG2';

    private const ENG1_RELEGATION_PLAYOFF_POSITION = 13;
    private const ENG2_PROMOTION_PLAYOFF_POSITION = 2;
    private const ENG2_DIRECT_PROMOTION_POSITION = 1;

    private string $perspective = self::ENG1_ID;

    public function __construct(
        private readonly string $competitionId = self::PLAYOFF_ID,
        private readonly int $directCount = 1,
        private readonly int $playoffCount = 0,
        private readonly int $triggerMatchday = 26,
    ) {
    }

    /**
     * Return a clone answering for the given division (ENG1 or ENG2).
     */
    public function withPerspective(string $competitionId): self
    {
        $clone = clone $this;
        $clone->perspective = $competitionId;
        return $clone;
    }

    public function getCompetitionId(): string
    {
        return $this->competitionId;
    }

    public function getQualifyingPositions(): array
    {
        return $this->perspective === self::ENG2_ID
            ? [self::ENG2_PROMOTION_PLAYOFF_POSITION]
            : [self::ENG1_RELEGATION_PLAYOFF_POSITION];
    }

    public function getDirectPromotionPositions(): array
    {
        // ENG1 is the top tier: nothing to be promoted to. ENG2's champion
        // goes up directly.
        return $this->perspective === self::ENG2_ID
            ? [self::ENG2_DIRECT_PROMOTION_POSITION]
            : [];
    }

    /**
     * Lang key for the qualifying (playoff) positions label. ENG1's 13th
     * fights to avoid relegation; ENG2's 2nd fights for promotion.
     */
    public function getQualifyingLabel(): string
    {
        return $this->perspective === self::ENG2_ID
            ? 'cup.promotion_playoff'
            : 'cup.relegation_playoff';
    }

    public function getTriggerMatchday(): int
    {
        return $this->triggerMatchday;
    }

    public function getTotalRounds(): int
    {
        return 1;
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

    /**
     * Single matchup: [ENG2 2nd (hosts), ENG1 13th].
     *
     * @return array<array{0: string, 1: string}>
     */
    public function generateMatchups(Game $game, int $round): array
    {
        if ($round !== 1) {
            throw new \InvalidArgumentException("Invalid playoff round: {$round}");
        }

        $eng1Team = $this->teamAtPosition($game, self::ENG1_ID, self::ENG1_RELEGATION_PLAYOFF_POSITION);
        $eng2Team = $this->teamAtPosition($game, self::ENG2_ID, self::ENG2_PROMOTION_PLAYOFF_POSITION);

        if ($eng1Team === null || $eng2Team === null) {
            throw new \RuntimeException('Could not determine WSL relegation playoff participants');
        }

        $this->populateCompetitionEntries($game, [$eng1Team, $eng2Team]);

        // ENG2 team hosts the single match.
        return [[$eng2Team, $eng1Team]];
    }

    public function isComplete(Game $game): bool
    {
        $tie = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->where('round_number', 1)
            ->first();

        return $tie !== null && $tie->completed === true && $tie->winner_id !== null;
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
     * Team UUID at a 1-based standings position. Uses real standings for
     * the player's league, lazy full-season simulation for the other one
     * (the game never simulates leagues the player isn't in).
     */
    private function teamAtPosition(Game $game, string $leagueId, int $position): ?string
    {
        $ordered = $leagueId === $game->competition_id
            ? $this->realStandingsOrder($game, $leagueId)
            : $this->simulatedOrder($game, $leagueId);

        return $ordered[$position - 1] ?? null;
    }

    /**
     * @return list<string> Team UUIDs ordered 1st..last from real standings.
     */
    private function realStandingsOrder(Game $game, string $leagueId): array
    {
        return GameStanding::where('game_id', $game->id)
            ->where('competition_id', $leagueId)
            ->orderBy('position')
            ->pluck('team_id')
            ->all();
    }

    /**
     * @return list<string> Team UUIDs ordered 1st..last from a lazy simulation.
     */
    private function simulatedOrder(Game $game, string $leagueId): array
    {
        $existing = SimulatedSeason::where('game_id', $game->id)
            ->where('competition_id', $leagueId)
            ->where('season', $game->season)
            ->first();

        if ($existing && !empty($existing->results)) {
            return array_values($existing->results);
        }

        $teams = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $leagueId)
            ->pluck('team_id')
            ->all();

        if (empty($teams)) {
            return [];
        }

        $ordered = app(SeasonSimulationService::class)->simulateLeague($game, $teams);

        SimulatedSeason::updateOrCreate(
            [
                'game_id' => $game->id,
                'competition_id' => $leagueId,
                'season' => $game->season,
            ],
            ['results' => array_values($ordered)]
        );

        return array_values($ordered);
    }

    private function populateCompetitionEntries(Game $game, array $teamIds): void
    {
        foreach ($teamIds as $teamId) {
            CompetitionEntry::firstOrCreate(
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
