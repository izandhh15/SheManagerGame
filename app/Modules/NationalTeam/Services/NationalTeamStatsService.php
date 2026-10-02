<?php

namespace App\Modules\NationalTeam\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stats for national-team squad members:
 * - With the national team: caps and goals (from this game's matches).
 * - With their club this season: appearances, goals, assists — REAL data
 *   from the dual club game when available (same player_id), never invented.
 */
class NationalTeamStatsService
{
    /**
     * Caps and goals with the national team, keyed by game_player_id.
     *
     * @return array<string, array{caps: int, goals: int}>
     */
    public function nationalStats(Game $game, string $teamId): array
    {
        $matches = GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId);
            })
            ->get(['id', 'home_team_id', 'home_lineup', 'away_lineup']);

        if ($matches->isEmpty()) {
            return [];
        }

        $matchIds = $matches->pluck('id')->all();
        $isHome = $matches->keyBy('id')->map(fn ($m) => $m->home_team_id === $teamId);

        // Caps: player in the lineup.
        $caps = [];
        foreach ($matches as $match) {
            $lineup = $isHome[$match->id] ? $match->home_lineup : $match->away_lineup;
            foreach ($lineup ?? [] as $entry) {
                $gpId = $entry['game_player_id'] ?? $entry['id'] ?? null;
                if ($gpId) {
                    $caps[$gpId] = ($caps[$gpId] ?? 0) + 1;
                }
            }
        }

        // Goals: match events.
        $goals = MatchEvent::whereIn('game_match_id', $matchIds)
            ->where('event_type', 'goal')
            ->select('game_player_id', DB::raw('count(*) as total'))
            ->groupBy('game_player_id')
            ->pluck('total', 'game_player_id')
            ->all();

        $stats = [];
        foreach ($caps as $gpId => $capCount) {
            $stats[$gpId] = [
                'caps' => $capCount,
                'goals' => (int) ($goals[$gpId] ?? 0),
            ];
        }

        return $stats;
    }

    /**
     * This season's club stats (appearances, goals, assists), keyed by
     * player_id (template ID). Only from the dual club game — never invented.
     *
     * @return array<string, array{club: string, apps: int, goals: int, assists: int}>
     */
    public function clubStats(Game $nationalGame, array $playerIds): array
    {
        if (empty($playerIds)) {
            return [];
        }

        $clubGame = $nationalGame->dualPartner();
        if (! $clubGame || $clubGame->isTournamentMode()) {
            return [];
        }

        // Map player_id -> GamePlayer in the club game, with match state.
        $players = GamePlayer::where('game_id', $clubGame->id)
            ->whereIn('player_id', $playerIds)
            ->with('matchState')
            ->get();

        $stats = [];
        foreach ($players as $gp) {
            $state = $gp->matchState;
            $stats[$gp->player_id] = [
                'club' => $gp->team?->name ?? '',
                'apps' => (int) ($state?->season_appearances ?? 0),
                'goals' => (int) ($state?->goals ?? 0),
                'assists' => (int) ($state?->assists ?? 0),
            ];
        }

        return $stats;
    }
}
