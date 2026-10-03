<?php

namespace App\Modules\Match\Services;

use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayerMatchState;
use App\Models\MatchEvent;
use App\Modules\Competition\Services\PlayoffTiebreakerService;
use App\Modules\Match\DTOs\ExtraTimeProcessResult;
use App\Modules\Match\DTOs\PenaltyProcessResult;
use App\Modules\Match\DTOs\TacticalConfig;
use App\Modules\Match\Enums\MatchPhase;
use App\Modules\Match\Support\ScoreEventsAuditor;
use App\Modules\Match\Support\StoppageCalculator;
use App\Modules\Match\Support\StoppageDurations;
use Illuminate\Support\Collection;

class ExtraTimeAndPenaltyService
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
        private readonly MatchEventRepository $matchEventRepository,
        private readonly PlayoffTiebreakerService $playoffTiebreakerService,
        private readonly MatchLineupResolver $lineupResolver = new MatchLineupResolver,
        private readonly StoppageCalculator $stoppageCalculator = new StoppageCalculator,
    ) {}

    /**
     * Simulate extra time for a live match, persist scores and events.
     *
     * Expects $match to have homeTeam/awayTeam loaded (or lazy-loadable).
     */
    public function processExtraTime(GameMatch $match, Game $game): ExtraTimeProcessResult
    {
        // R10: idempotency guard — a second POST (double click) must not
        // re-simulate extra time and duplicate the ET events (ghost goals).
        // Mirrors the ProcessPenalties guard on home_score_penalties !== null.
        if ($match->is_extra_time) {
            return $this->alreadyProcessedResult($match);
        }

        [$homePlayers, $awayPlayers] = $this->loadPlayersByTeam($match);

        $homeEntryMinutes = [];
        $awayEntryMinutes = [];

        foreach ($match->substitutions ?? [] as $sub) {
            if ($sub['team_id'] === $match->home_team_id) {
                $homeEntryMinutes[$sub['player_in_id']] = $sub['minute'];
            } else {
                $awayEntryMinutes[$sub['player_in_id']] = $sub['minute'];
            }
        }

        $tc = TacticalConfig::fromMatch($match);

        $homePlayerSlots = $match->playerSlotMap('home');
        $awayPlayerSlots = $match->playerSlotMap('away');

        $extraTimeResult = $this->matchSimulator->simulateExtraTime(
            $match->homeTeam,
            $match->awayTeam,
            $homePlayers,
            $awayPlayers,
            $homeEntryMinutes,
            $awayEntryMinutes,
            homeFormation: $tc->homeFormation,
            awayFormation: $tc->awayFormation,
            homeMentality: $tc->homeMentality,
            awayMentality: $tc->awayMentality,
            homePlayingStyle: $tc->homePlayingStyle,
            awayPlayingStyle: $tc->awayPlayingStyle,
            homePressing: $tc->homePressing,
            awayPressing: $tc->awayPressing,
            homeDefLine: $tc->homeDefLine,
            awayDefLine: $tc->awayDefLine,
            neutralVenue: $match->isNeutralVenue(),
            homePlayerSlots: $homePlayerSlots,
            awayPlayerSlots: $awayPlayerSlots,
            stoppage: StoppageDurations::fromMatch($match),
        );

        // Derive ET stoppage from the event mix; persist before storing events
        // so MatchEventRepository decomposes raw minutes correctly.
        $etStoppage = $this->stoppageCalculator->calculateExtraTime(
            $extraTimeResult->events,
            regulationStoppage: (int) ($match->first_half_stoppage ?? 0)
                + (int) ($match->second_half_stoppage ?? 0),
        );

        $match->update([
            'is_extra_time' => true,
            'home_score_et' => $extraTimeResult->homeScore,
            'away_score_et' => $extraTimeResult->awayScore,
            'home_possession' => $extraTimeResult->homePossession,
            'away_possession' => $extraTimeResult->awayPossession,
            'et_first_half_stoppage' => $etStoppage['et_first_half'],
            'et_second_half_stoppage' => $etStoppage['et_second_half'],
        ]);

        $storedEvents = $this->storeExtraTimeEvents($match, $game, $extraTimeResult->events);

        ScoreEventsAuditor::audit($match->refresh(), 'process_extra_time');

        $needsPenalties = $this->checkNeedsPenalties($match, $extraTimeResult->homeScore, $extraTimeResult->awayScore);

        return new ExtraTimeProcessResult(
            homeScoreET: $extraTimeResult->homeScore,
            awayScoreET: $extraTimeResult->awayScore,
            storedEvents: $storedEvents,
            needsPenalties: $needsPenalties,
            homePossession: $extraTimeResult->homePossession,
            awayPossession: $extraTimeResult->awayPossession,
        );
    }

    /**
     * R10: rebuild the ET result from already-persisted state when
     * processExtraTime() is called again — no re-simulation, no score
     * overwrite, no duplicated events.
     */
    private function alreadyProcessedResult(GameMatch $match): ExtraTimeProcessResult
    {
        $etEvents = MatchEvent::with('gamePlayer')
            ->where('game_match_id', $match->id)
            ->orderedChronologically()
            ->get()
            ->filter(fn (MatchEvent $e) => $e->phase->isExtraTime())
            ->values();

        $homeScoreET = $match->home_score_et ?? 0;
        $awayScoreET = $match->away_score_et ?? 0;

        return new ExtraTimeProcessResult(
            homeScoreET: $homeScoreET,
            awayScoreET: $awayScoreET,
            storedEvents: $etEvents,
            needsPenalties: $this->checkNeedsPenalties($match, $homeScoreET, $awayScoreET),
            homePossession: $match->home_possession ?? 50,
            awayPossession: $match->away_possession ?? 50,
        );
    }

    /**
     * R13: extra-time goals/assists must reach the players' season stats
     * (GamePlayerMatchState), not just the event feed — otherwise the
     * scoring charts stay incomplete in knockout ties with extra time.
     * Uses the same event→column mapping as MatchResultProcessor and
     * MatchResimulationService::applyNewEvents().
     *
     * @param  Collection<MatchEvent>  $storedEvents  Persisted ET event models
     */
    public static function applyExtraTimePlayerStats(Collection $storedEvents): void
    {
        $statIncrements = [];

        foreach ($storedEvents as $event) {
            $playerId = $event->game_player_id;

            // A squad-less side's goal has no scorer whose record to touch.
            if ($playerId === MatchEvent::UNATTRIBUTED_PLAYER_ID) {
                continue;
            }

            $column = match ($event->event_type) {
                'goal' => 'goals',
                'own_goal' => 'own_goals',
                'assist' => 'assists',
                default => null,
            };

            if ($column === null) {
                continue;
            }

            $statIncrements[$playerId][$column] = ($statIncrements[$playerId][$column] ?? 0) + 1;
        }

        $statIncrements = array_filter($statIncrements, fn (array $inc) => ! empty($inc));

        if (! empty($statIncrements)) {
            GamePlayerMatchState::bulkIncrementStats($statIncrements);
        }
    }

    /**
     * Simulate a penalty shootout for a live match, persist scores.
     */
    public function processPenalties(GameMatch $match, Game $game, array $userKickerOrder): PenaltyProcessResult
    {
        [$homePlayers, $awayPlayers] = $this->loadPlayersByTeam($match);

        $isUserHome = $match->home_team_id === $game->team_id;

        $result = $this->matchSimulator->simulatePenaltyShootout(
            $homePlayers,
            $awayPlayers,
            $isUserHome ? $userKickerOrder : null,
            $isUserHome ? null : $userKickerOrder,
        );

        $match->update([
            'home_score_penalties' => $result['homeScore'],
            'away_score_penalties' => $result['awayScore'],
        ]);

        return new PenaltyProcessResult(
            homeScore: $result['homeScore'],
            awayScore: $result['awayScore'],
            kicks: $result['kicks'],
        );
    }

    /**
     * Load the players currently on the pitch, accounting for substitutions
     * and red cards.
     *
     * @return array{0: Collection, 1: Collection} [homePlayers, awayPlayers]
     */
    private function loadPlayersByTeam(GameMatch $match): array
    {
        return $this->lineupResolver->playersOnPitchAtEnd($match);
    }

    /**
     * Determine if penalties are needed after extra time,
     * accounting for two-legged aggregate scores.
     */
    /**
     * Rebuild the ET payload for a page-refresh scenario where extra time
     * has already been simulated — so the client can skip the animation
     * and restore the right state (already at penalties, needs penalties,
     * or ET settled the match).
     *
     * @return array<string, mixed>
     */
    public function buildRefreshState(GameMatch $match): array
    {
        $etEvents = $match->events->filter(fn ($e) => $e->phase->isExtraTime());

        $state = [
            'extraTimeEvents' => MatchResimulationService::formatMatchEvents($etEvents),
            'homeScoreET' => $match->home_score_et ?? 0,
            'awayScoreET' => $match->away_score_et ?? 0,
            'penalties' => null,
            'needsPenalties' => false,
        ];

        if ($match->home_score_penalties !== null) {
            $state['penalties'] = [
                'home' => $match->home_score_penalties,
                'away' => $match->away_score_penalties,
            ];
        } else {
            // ET done but penalties not yet resolved — check whether the ET
            // result is actually a draw. Without this, a page refresh after
            // ET ended 2-1 would incorrectly send the user to penalties.
            $state['needsPenalties'] = $this->checkNeedsPenalties(
                $match, $match->home_score_et ?? 0, $match->away_score_et ?? 0
            );
        }

        return $state;
    }

    public function checkNeedsPenalties(GameMatch $match, int $homeScoreET, int $awayScoreET): bool
    {
        $totalHome = $match->home_score + $homeScoreET;
        $totalAway = $match->away_score + $awayScoreET;

        $cupTie = null;
        if ($match->cup_tie_id) {
            $cupTie = CupTie::with('firstLegMatch')->find($match->cup_tie_id);

            if ($cupTie && $cupTie->second_leg_match_id === $match->id) {
                $firstLeg = $cupTie->firstLegMatch;
                if ($firstLeg?->played) {
                    // Second leg home = tie's away, so swap for aggregate
                    $totalHome = ($firstLeg->home_score ?? 0) + ($match->away_score + $awayScoreET);
                    $totalAway = ($firstLeg->away_score ?? 0) + ($match->home_score + $homeScoreET);
                }
            }
        }

        if ($totalHome !== $totalAway) {
            return false;
        }

        // Promotion-playoff ties are decided by regular-season position when
        // level after extra time — no penalty shootout.
        if ($cupTie && $this->playoffTiebreakerService->appliesTo($cupTie)) {
            return false;
        }

        return true;
    }

    /**
     * Persist extra time events as MatchEvent records.
     *
     * @return Collection<MatchEvent>
     */
    private function storeExtraTimeEvents(GameMatch $match, Game $game, Collection $events): Collection
    {
        $ids = $this->matchEventRepository->bulkInsert($events, $game->id, $match->id);

        if (empty($ids)) {
            return collect();
        }

        $stored = MatchEvent::with('gamePlayer')
            ->whereIn('id', $ids)
            ->orderedChronologically()
            ->get();

        // R13: ET goals/assists reach season stats, not just the event feed.
        self::applyExtraTimePlayerStats($stored);

        return $stored;
    }

}
