<?php

namespace App\Modules\Player\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Player club history, derived ONLY from the game's transfer ledger
 * (game_transfers). There is no pre-game history — inventing a past
 * is forbidden — so the history is what the game has recorded.
 *
 * History is reconstructed as a chain: the earliest from_team, then each
 * to_team in order, ending with the player's current club. Cycles (a
 * player returning to a former club) are preserved once, e.g.
 * [Valencia, Madrid, Valencia].
 */
class PlayerHistoryService
{
    /**
     * Chronological club history (team IDs): earliest club first,
     * current club last. Consecutive duplicates are collapsed.
     *
     * @return Collection<int, string>
     */
    public function clubHistory(Game $game, GamePlayer $player): Collection
    {
        // Season-ordered: the earliest transfer tells us where the path starts
        // (matters when the chain cycles, e.g. B->A->B).
        $transfers = GameTransfer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->orderBy('season')
            ->get();

        if ($transfers->isEmpty()) {
            return collect([$player->team_id])->filter()->values();
        }

        // from_team -> to_team links (a player follows a linear path).
        $next = [];
        foreach ($transfers as $t) {
            if ($t->from_team_id) {
                $next[$t->from_team_id] = $t->to_team_id;
            }
        }

        $toTeams = $transfers->pluck('to_team_id')->all();

        // Start: a from_team that is never a to_team (the earliest club).
        // In a cycle (return to a former club) every from_team is also a
        // to_team: fall back to the earliest transfer's from_team.
        $start = null;
        foreach ($transfers as $t) {
            if ($t->from_team_id && ! in_array($t->from_team_id, $toTeams, true)) {
                $start = $t->from_team_id;
                break;
            }
        }
        if ($start === null) {
            $first = $transfers->first();
            $start = $first->from_team_id ?? $first->to_team_id;
        }

        // Walk the chain, stopping on cycles (a return is kept once).
        $history = [$start];
        $seen = [$start => true];
        $guard = 0;
        while (isset($next[end($history)]) && ! isset($seen[$next[end($history)]]) && $guard++ < 50) {
            $nextTeam = $next[end($history)];
            $history[] = $nextTeam;
            $seen[$nextTeam] = true;
        }

        // The current club always closes the history.
        if ($player->team_id && end($history) !== $player->team_id) {
            $history[] = $player->team_id;
        }

        // Collapse consecutive duplicates.
        $deduped = [];
        foreach ($history as $teamId) {
            if (end($deduped) !== $teamId) {
                $deduped[] = $teamId;
            }
        }

        return collect($deduped)->filter()->values();
    }

    /**
     * Clubs from earlier stints, unique, in order. The current stint is
     * excluded, but an earlier stint at the current club counts (that is
     * what makes a signing a homecoming).
     *
     * @return Collection<int, string>
     */
    public function formerClubIds(Game $game, GamePlayer $player): Collection
    {
        $history = $this->clubHistory($game, $player);
        if ($history->count() < 2) {
            return collect();
        }

        return $history->slice(0, -1)->unique()->values();
    }

    /**
     * Former club names, for narratives.
     *
     * @return Collection<int, string>
     */
    public function formerClubNames(Game $game, GamePlayer $player): Collection
    {
        return $this->formerClubIds($game, $player)
            ->map(fn ($id) => Team::find($id)?->name)
            ->filter()
            ->values();
    }

    /**
     * Does the player face a former club in this match? ("vuelve a casa")
     */
    public function returnsHomeAgainst(Game $game, GamePlayer $player, string $opponentTeamId): bool
    {
        if ($player->team_id === $opponentTeamId) {
            return false;
        }

        return $this->formerClubIds($game, $player)->contains($opponentTeamId);
    }

    /**
     * Is this signing a homecoming? (the player already played for $clubId
     * in an earlier stint — e.g. sold and re-signed).
     */
    public function isHomecomingSigning(Game $game, GamePlayer $player, string $clubId): bool
    {
        return $this->formerClubIds($game, $player)->contains($clubId);
    }
}
