<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\GamePlayerMatchState;
use App\Models\MatchEvent;
use App\Modules\Match\Enums\MatchPhase;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R13 [ALTA]: los goles/asistencias de la prórroga solo se persistían como
 * eventos (`bulkInsert`) y nunca llegaban a las stats de temporada
 * (`GamePlayerMatchState`): pichichi incompleto en eliminatorias con
 * prórroga.
 *
 * El fix aplica los incrementos (goals/own_goals/assists) tras persistir los
 * eventos de ET, tanto en `ExtraTimeAndPenaltyService::storeExtraTimeEvents()`
 * (partido en directo) como en las dos ramas de `CupTieResolver` (IA-vs-IA).
 */
class R13ExtraTimeStatsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReviewScenario;

    private function makeEtEvent(string $gameId, string $matchId, string $teamId, string $playerId, string $type): MatchEvent
    {
        return MatchEvent::create([
            'game_id' => $gameId,
            'game_match_id' => $matchId,
            'game_player_id' => $playerId,
            'team_id' => $teamId,
            'minute' => 95,
            'phase' => MatchPhase::ET_FIRST_HALF->value,
            'stoppage_minute' => null,
            'event_type' => $type,
            'metadata' => null,
        ]);
    }

    private function seasonGoals(string $playerId): int
    {
        return (int) GamePlayerMatchState::where('game_player_id', $playerId)->value('goals');
    }

    private function seasonAssists(string $playerId): int
    {
        return (int) GamePlayerMatchState::where('game_player_id', $playerId)->value('assists');
    }

    public function test_extra_time_goals_and_assists_reach_season_stats(): void
    {
        [$game, $team, , $match, $homeLineup] = $this->buildReviewScenario();

        $scorer = $homeLineup[8];
        $assister = $homeLineup[7];

        $events = collect([
            $this->makeEtEvent($game->id, $match->id, $team->id, $scorer->id, 'goal'),
            $this->makeEtEvent($game->id, $match->id, $team->id, $scorer->id, 'goal'),
            $this->makeEtEvent($game->id, $match->id, $team->id, $assister->id, 'assist'),
        ]);

        ExtraTimeAndPenaltyService::applyExtraTimePlayerStats($events);

        $this->assertSame(2, $this->seasonGoals($scorer->id));
        $this->assertSame(1, $this->seasonAssists($assister->id));
        $this->assertSame(0, $this->seasonGoals($assister->id));
    }

    public function test_unattributed_and_non_scoring_events_are_ignored(): void
    {
        [$game, $team, , $match, $homeLineup] = $this->buildReviewScenario();

        $player = $homeLineup[9];

        $events = collect([
            $this->makeEtEvent($game->id, $match->id, $team->id, MatchEvent::UNATTRIBUTED_PLAYER_ID, 'goal'),
            $this->makeEtEvent($game->id, $match->id, $team->id, $player->id, 'yellow_card'),
            $this->makeEtEvent($game->id, $match->id, $team->id, $player->id, 'substitution'),
        ]);

        // No debe lanzar ni tocar stats: goles sin goleadora y eventos que no
        // puntúan se ignoran.
        ExtraTimeAndPenaltyService::applyExtraTimePlayerStats($events);

        $this->assertSame(0, $this->seasonGoals($player->id));
        $this->assertSame(0, $this->seasonAssists($player->id));
    }

    public function test_own_goals_reach_season_stats(): void
    {
        [$game, $team, , $match, $homeLineup] = $this->buildReviewScenario();

        $player = $homeLineup[3];

        ExtraTimeAndPenaltyService::applyExtraTimePlayerStats(collect([
            $this->makeEtEvent($game->id, $match->id, $team->id, $player->id, 'own_goal'),
        ]));

        $this->assertSame(
            1,
            (int) GamePlayerMatchState::where('game_player_id', $player->id)->value('own_goals')
        );
    }
}
