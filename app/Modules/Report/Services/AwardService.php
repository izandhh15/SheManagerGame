<?php

namespace App\Modules\Report\Services;

use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use Illuminate\Support\Collection;

class AwardService
{
    /**
     * League-agnostic top scorers by default; pass $competitionId to count
     * goals from that competition's matches only (B26). The season-wide
     * game_player_match_state aggregate cannot answer "league top scorer"
     * because it mixes every competition.
     *
     * @return Collection<int, GamePlayer>
     */
    public function getTopScorers(string $gameId, Collection|array|null $teamIds = null, int $limit = 5, ?string $competitionId = null): Collection
    {
        if ($competitionId !== null) {
            return $this->getCompetitionTopScorers($gameId, $teamIds, $limit, $competitionId);
        }

        return GamePlayer::with(['team', 'matchState'])
            ->joinMatchState()
            ->where('game_players.game_id', $gameId)
            ->when($teamIds, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->whereMatchStat('goals', '>', 0)
            ->orderByMatchStat('goals')
            ->orderByMatchStat('assists')
            ->orderByMatchStat('appearances', 'asc')
            // Deterministic final tiebreak (B25): with identical goals,
            // assists and appearances the winner falls back to the lowest
            // player id. This is NOT sporting merit — it only guarantees a
            // stable, reproducible order instead of whatever row order the
            // database engine happens to return.
            ->orderBy('game_players.id', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Top scorers of a single competition, counted from that competition's
     * match events (goals + assists). Same event-based approach as
     * CompetitionViewService::getTopScorers() and the MVP's competition_id
     * filtering. The returned players carry the competition goal tally as an
     * in-memory `goals` override (honoured by GamePlayer::matchStateValue()),
     * so views keep reading $scorer->goals unchanged.
     *
     * Tiebreaks: competition goals DESC, competition assists DESC, then the
     * deterministic player-id ASC fallback (B25). Per-competition appearances
     * are not available without structural changes, so they are skipped.
     *
     * @return Collection<int, GamePlayer>
     */
    private function getCompetitionTopScorers(string $gameId, Collection|array|null $teamIds, int $limit, string $competitionId): Collection
    {
        $tallyRows = MatchEvent::where('match_events.game_id', $gameId)
            ->whereIn('match_events.event_type', [MatchEvent::TYPE_GOAL, MatchEvent::TYPE_ASSIST])
            // A squad-less cup entrant's goals share one sentinel scorer, so
            // they would aggregate into a phantom leader (same guard as
            // CompetitionViewService::getTopScorers()).
            ->where('match_events.game_player_id', '!=', MatchEvent::UNATTRIBUTED_PLAYER_ID)
            ->join('game_matches', 'game_matches.id', '=', 'match_events.game_match_id')
            ->where('game_matches.competition_id', $competitionId)
            ->selectRaw(
                'match_events.game_player_id, ' .
                "SUM(CASE WHEN match_events.event_type = '" . MatchEvent::TYPE_GOAL . "' THEN 1 ELSE 0 END) as goals, " .
                "SUM(CASE WHEN match_events.event_type = '" . MatchEvent::TYPE_ASSIST . "' THEN 1 ELSE 0 END) as assists"
            )
            ->groupBy('match_events.game_player_id')
            ->get();

        if ($tallyRows->isEmpty()) {
            return collect();
        }

        $players = GamePlayer::with(['team', 'matchState'])
            ->where('game_players.game_id', $gameId)
            ->whereIn('id', $tallyRows->pluck('game_player_id')->unique())
            ->when($teamIds, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->get()
            ->keyBy('id');

        return $tallyRows
            ->map(function ($row) use ($players) {
                $player = $players->get($row->game_player_id);
                if (! $player || (int) $row->goals === 0) {
                    return null;
                }
                $player = clone $player;
                // Display the competition tally instead of the season-wide total.
                $player->goals = (int) $row->goals;
                $player->setAttribute('_competition_assists', (int) $row->assists);

                return $player;
            })
            ->filter()
            ->sortBy([
                ['goals', 'desc'],
                ['_competition_assists', 'desc'],
                ['id', 'asc'],
            ])
            ->take($limit)
            ->values();
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
     * @return array{Collection, ?object, Collection} [$topMvps, $teamMvpLeader, $mvpCounts]
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
