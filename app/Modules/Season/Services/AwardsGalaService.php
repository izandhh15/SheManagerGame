<?php

namespace App\Modules\Season\Services;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\SeasonAward;
use App\Modules\Report\Services\AwardService;

/**
 * Computes the season awards gala ("Gala de premios") winners from the
 * season's real simulated data:
 *
 *  - Balón de Oro: best player of the league by composite season score
 *  - Pichichi:    league top scorer
 *  - Zamora:      goalkeeper with fewest goals conceded per match
 *                   (with a proportional minimum-appearances bar)
 *  - MVP:         player with the most match-MVP awards
 *
 * Winners are league-scoped: only players from teams entered in the game's
 * main competition. Stats (goals, assists, clean sheets, MVP awards) come
 * from the simulated season.
 *
 * @return array<string, array{player: GamePlayer, detail: array}> keyed by
 *         SeasonAward::AWARD_* constants; awards without an eligible winner
 *         are simply absent.
 */
class AwardsGalaService
{
    public function __construct(
        private readonly AwardService $awardService,
    ) {}

    public function computeWinners(Game $game): array
    {
        if ($game->isTournamentMode()) {
            return [];
        }

        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->pluck('team_id');

        if ($teamIds->isEmpty()) {
            return [];
        }

        $winners = [];

        $pichichi = $this->awardService
            ->getTopScorers($game->id, $teamIds, limit: 1)
            ->first();
        if ($pichichi) {
            $winners[SeasonAward::AWARD_PICHICHI] = [
                'player' => $pichichi,
                'detail' => ['goals' => $pichichi->goals],
            ];
        }

        $zamora = $this->zamoraWinner($game, $teamIds);
        if ($zamora) {
            $winners[SeasonAward::AWARD_ZAMORA] = [
                'player' => $zamora,
                'detail' => [
                    'goals_conceded' => $zamora->goals_conceded,
                    'appearances' => $zamora->appearances,
                    'goals_conceded_per_match' => round(
                        $zamora->goals_conceded / max(1, $zamora->appearances), 2
                    ),
                    'clean_sheets' => $zamora->clean_sheets,
                ],
            ];
        }

        $mvp = $this->mvpWinner($game, $teamIds);
        if ($mvp) {
            $winners[SeasonAward::AWARD_MVP] = [
                'player' => $mvp['player'],
                'detail' => ['mvp_awards' => $mvp['count']],
            ];
        }

        $ballonDor = $this->ballonDorWinner($game, $teamIds);
        if ($ballonDor) {
            $winners[SeasonAward::AWARD_BALLON_DOR] = [
                'player' => $ballonDor,
                'detail' => [
                    'goals' => $ballonDor->goals,
                    'assists' => $ballonDor->assists,
                    'clean_sheets' => $ballonDor->clean_sheets,
                ],
            ];
        }

        return $winners;
    }

    /**
     * Zamora with a proportional minimum-appearances bar: ~70% of the
     * league's matches (the real award requires 28 of 38).
     */
    private function zamoraWinner(Game $game, $teamIds): ?GamePlayer
    {
        $maxPlayed = (int) GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->max('played');

        $minAppearances = $maxPlayed > 0
            ? max(3, (int) ceil($maxPlayed * 0.7))
            : 3;

        return $this->awardService->getZamoraWinner($game->id, $teamIds, $minAppearances);
    }

    /**
     * @return array{player: GamePlayer, count: int}|null
     */
    private function mvpWinner(Game $game, $teamIds): ?array
    {
        [$topMvps] = $this->awardService->getMvpRankings(
            $game->id,
            $game->competition_id,
            $game->team_id,
            limit: 1
        );

        $top = $topMvps->first();

        if (! $top || ! $top->gamePlayer) {
            return null;
        }

        if (! $teamIds->contains($top->gamePlayer->team_id)) {
            return null;
        }

        return ['player' => $top->gamePlayer, 'count' => $top->count];
    }

    /**
     * Best player of the league by composite season score:
     * goals weigh most, then assists, clean sheets and match-MVP awards.
     */
    private function ballonDorWinner(Game $game, $teamIds): ?GamePlayer
    {
        $mvpCounts = GameMatch::mvpCountsByPlayer(
            $game->id,
            $game->competition_id,
            $teamIds->all()
        );

        $players = GamePlayer::with(['team', 'matchState'])
            ->joinMatchState()
            ->where('game_players.game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->whereMatchStat('appearances', '>', 0)
            ->get();

        if ($players->isEmpty()) {
            return null;
        }

        // NOTE: Collection::sortBy() invokes a closure criterion as a
        // two-argument comparator ($a, $b), not as a value extractor, so
        // the composite score is materialised as an attribute first.
        foreach ($players as $player) {
            $player->setAttribute(
                '_gala_score',
                $this->ballonDorScore($player, $mvpCounts->get($player->id, 0))
            );
        }

        return $players->sortBy([
            ['_gala_score', 'desc'],
            ['goals', 'desc'],
            ['assists', 'desc'],
            ['appearances', 'asc'],
            ['name', 'asc'],
        ])->first();
    }

    private function ballonDorScore(GamePlayer $player, int $mvpAwards): float
    {
        return $player->goals * 3
            + $player->assists * 2
            + $player->clean_sheets * 1
            + $mvpAwards * 4;
    }
}
