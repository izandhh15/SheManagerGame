<?php

namespace App\Modules\Report\Services;

use App\Models\GameMatch;
use App\Models\GamePlayer;
use Illuminate\Support\Collection;

class AwardService
{
    /**
     * @return Collection<int, GamePlayer>
     */
    public function getTopScorers(string $gameId, Collection|array|null $teamIds = null, int $limit = 5): Collection
    {
        return GamePlayer::with(['team', 'matchState'])
            ->joinMatchState()
            ->where('game_players.game_id', $gameId)
            ->when($teamIds, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->whereMatchStat('goals', '>', 0)
            ->orderByMatchStat('goals')
            ->orderByMatchStat('assists')
            ->orderByMatchStat('appearances', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, GamePlayer>
     */
    public function getTopAssisters(string $gameId, Collection|array|null $teamIds = null, int $limit = 5): Collection
    {
        return GamePlayer::with(['team', 'matchState'])
            ->joinMatchState()
            ->where('game_players.game_id', $gameId)
            ->when($teamIds, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->whereMatchStat('assists', '>', 0)
            ->orderByMatchStat('assists')
            ->orderByMatchStat('goals')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, GamePlayer>
     */
    public function getTopGoalkeepers(string $gameId, Collection|array|null $teamIds = null, int $minAppearances = 3, int $limit = 5): Collection
    {
        return GamePlayer::with(['team', 'matchState'])
            ->joinMatchState()
            ->where('game_players.game_id', $gameId)
            ->when($teamIds, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->where('position', 'Goalkeeper')
            ->whereMatchStat('appearances', '>=', $minAppearances)
            ->get()
            ->sortBy([
                ['clean_sheets', 'desc'],
                [fn ($gk) => $gk->appearances > 0 ? $gk->goals_conceded / $gk->appearances : 999, 'asc'],
            ])
            ->take($limit)
            ->values();
    }

    /**
     * Build MVP rankings: top MVPs and the user's team MVP leader.
     *
     * @return array{Collection, ?object} [$topMvps, $teamMvpLeader]
     */
    public function getMvpRankings(string $gameId, ?string $competitionId, string $teamId, int $limit = 5): array
    {
        $mvpCounts = GameMatch::mvpCountsByPlayer($gameId, $competitionId);

        if ($mvpCounts->isEmpty()) {
            return [collect(), null, $mvpCounts];
        }

        $players = GamePlayer::with(['team', 'matchState'])
            ->whereIn('id', $mvpCounts->keys()->all())
            ->get()
            ->keyBy('id');

        $ranked = $mvpCounts
            ->map(fn ($count, $playerId) => (object) [
                'gamePlayer' => $players->get($playerId),
                'count' => $count,
            ])
            ->filter(fn ($item) => $item->gamePlayer !== null)
            ->sortByDesc('count')
            ->values();

        $topMvps = $ranked->take($limit);
        $teamMvpLeader = $ranked->first(fn ($item) => $item->gamePlayer->team_id === $teamId);

        return [$topMvps, $teamMvpLeader, $mvpCounts];
    }

    /**
     * Zamora winner: goalkeeper with the fewest goals conceded per match
     * among those meeting the minimum appearances bar (the real Zamora
     * criterion). Contrast with getTopGoalkeepers(), which ranks by clean
     * sheets for the season-end sidebar.
     */
    public function getZamoraWinner(string $gameId, Collection|array|null $teamIds = null, int $minAppearances = 3): ?GamePlayer
    {
        $keepers = GamePlayer::with(['team', 'matchState'])
            ->joinMatchState()
            ->where('game_players.game_id', $gameId)
            ->when($teamIds, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->where('position', 'Goalkeeper')
            ->whereMatchStat('appearances', '>=', $minAppearances)
            ->get();

        // NOTE: sortBy() calls a closure criterion as a ($a, $b) comparator,
        // not as a value extractor, so the ratio is materialised first.
        foreach ($keepers as $keeper) {
            $keeper->setAttribute(
                '_goals_conceded_per_match',
                $keeper->appearances > 0
                    ? $keeper->goals_conceded / $keeper->appearances
                    : 999
            );
        }

        return $keepers->sortBy([
            ['_goals_conceded_per_match', 'asc'],
            ['clean_sheets', 'desc'],
            ['goals_conceded', 'asc'],
        ])->first();
    }

    /**
     * @return Collection<int, GamePlayer>
     */
    public function getTeamSquadStats(string $gameId, string $teamId): Collection
    {
        return GamePlayer::with(['matchState'])
            ->leftJoinMatchState()
            ->where('game_players.game_id', $gameId)
            ->where('team_id', $teamId)
            ->orderByMatchStat('appearances')
            ->get();
    }
}
