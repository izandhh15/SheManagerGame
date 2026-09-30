<?php

namespace App\Modules\Season\Services;

use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\SeasonArchive;
use App\Modules\Season\Jobs\SetupTournamentGame;
use Illuminate\Support\Facades\DB;

/**
 * Rolls a national-team career game over into its next competition when
 * the current one ends (Izan's unified multi-year calendar: WNL →
 * WQUEFA → WWCU27 → WNL → WEUROQ → WEURO → …).
 *
 * Tournament games are single-season by default (TournamentEnded →
 * snapshot + soft-delete). National-team games instead archive the
 * finished season, purge its data, reset the setup flag and re-dispatch
 * SetupTournamentGame — whose progression hook then advances
 * competition_id via TournamentCreationService::nextCompetitionInSequence()
 * (including the top-2 qualification gate for the final tournaments).
 *
 * The user's squad (GamePlayer rows) is deliberately kept: the same
 * national team carries its called-up 23 into the next competition.
 */
class NationalTeamRolloverService
{
    public function rollover(Game $game): void
    {
        DB::transaction(function () use ($game) {
            // Serialize concurrent end-of-tournament detections: the first
            // one to null the setup flag wins.
            $locked = Game::where('id', $game->id)->lockForUpdate()->first();
            if ($locked === null || $locked->setup_completed_at === null) {
                return;
            }

            // 1. Archive the finished season. Same final_standings shape as
            //    SeasonArchiveProcessor so the progression hook and the
            //    qualification gate read it identically.
            SeasonArchive::create([
                'game_id' => $game->id,
                'season' => $game->season,
                'final_standings' => $this->buildFinalStandings($game),
                'player_season_stats' => [],
                'season_awards' => [],
                'match_results' => [],
                'transfer_activity' => [],
            ]);

            // 2. Purge the finished season's competition data.
            DB::table('match_events')->where('game_id', $game->id)->delete();
            GameMatch::where('game_id', $game->id)->delete();
            GameStanding::where('game_id', $game->id)->delete();
            CompetitionEntry::where('game_id', $game->id)->delete();
            CupTie::where('game_id', $game->id)->delete();

            // 3. Reset for the new season. The competition switch itself
            //    happens in SetupTournamentGame's progression hook.
            $locked->update([
                'season' => (string) ((int) $game->season + 1),
                'setup_completed_at' => null,
            ]);
        });

        // Outside the transaction: re-run setup for the next competition.
        // (Re-check: a concurrent rollover may have claimed the game.)
        $fresh = Game::find($game->id);
        if ($fresh && $fresh->setup_completed_at === null) {
            SetupTournamentGame::dispatch(gameId: $fresh->id, teamId: $fresh->team_id);
        }
    }

    /**
     * Whether this game continues into another competition instead of
     * ending with the classic tournament snapshot + soft-delete.
     */
    public function isContinuable(Game $game): bool
    {
        if (($game->team?->type ?? null) !== 'national') {
            return false;
        }

        if (!in_array($game->competition_id, TournamentCreationService::NATIONAL_TEAM_COMPETITION_IDS, true)) {
            return false;
        }

        return TournamentCreationService::nextCompetitionInSequence($game->competition_id, $game) !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFinalStandings(Game $game): array
    {
        return GameStanding::where('game_id', $game->id)
            ->with('team')
            ->orderBy('position')
            ->get()
            ->map(fn (GameStanding $standing) => [
                'team_id' => $standing->team_id,
                'team_name' => $standing->team->name ?? 'Unknown',
                'competition_id' => $standing->competition_id,
                'position' => $standing->position,
                'played' => $standing->played,
                'won' => $standing->won,
                'drawn' => $standing->drawn,
                'lost' => $standing->lost,
                'goals_for' => $standing->goals_for,
                'goals_against' => $standing->goals_against,
                'goal_difference' => $standing->goal_difference,
                'points' => $standing->points,
            ])
            ->toArray();
    }
}
