<?php

namespace App\Modules\Social\Services;

use App\Models\CareerHistory;
use App\Models\Game;
use App\Models\ManagerStats;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Snapshots of deleted games so careers stay visible in histories even
 * after the save (and its rows) are gone. Snapshots are taken synchronously
 * in GameDeletionService::delete(), before the async cleanup job runs.
 */
class CareerHistoryService
{
    /**
     * Snapshot a game that is about to be deleted. Idempotent per game.
     */
    public function snapshotOnDelete(Game $game): ?CareerHistory
    {
        $existing = CareerHistory::where('user_id', $game->user_id)
            ->where('game_id', $game->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $team = $game->team;
        $stats = ManagerStats::where('user_id', $game->user_id)
            ->where('game_id', $game->id)
            ->first();

        return CareerHistory::create([
            'user_id' => $game->user_id,
            'game_id' => $game->id,
            'team_name' => $team?->name ?? __('friends.unknown_team'),
            'team_type' => $team?->type ?? 'club',
            'season' => $game->season,
            'stats' => [
                'matches' => (int) ($stats?->matches_played ?? 0),
                'wins' => (int) ($stats?->matches_won ?? 0),
                'draws' => (int) ($stats?->matches_drawn ?? 0),
                'losses' => (int) ($stats?->matches_lost ?? 0),
                'trophies' => (int) ($stats?->trophies_count ?? 0),
                'seasons_completed' => (int) ($stats?->seasons_completed ?? 0),
            ],
            'deleted_at' => now(),
        ]);
    }

    /**
     * Deleted-career snapshots for a user, most recent first.
     *
     * @return Collection<int, CareerHistory>
     */
    public function historyFor(User $user): Collection
    {
        return CareerHistory::where('user_id', $user->id)
            ->orderByDesc('deleted_at')
            ->get();
    }
}
