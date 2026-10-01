<?php

namespace App\Modules\Season\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Competition\Configs\FifaInternationalBreaks;
use Illuminate\Support\Facades\DB;

/**
 * National-team convocatoria logic (one call-up per FIFA window).
 *
 * The squad picker runs a few days before each international break —
 * never only at game creation. This service centralises the three
 * things every entry point needs:
 *
 *  - relevantWindow(): the window the user must pick a squad for
 *    (the current break if we're inside one, else the next one).
 *  - injuredPlayersUntil(): players who cannot be called up because
 *    they are injured in any of the user's saves (club and/or
 *    national — injuries carry over to the national team).
 *  - provisionalSquad(): the 23 highest-rated eligible players,
 *    used when the game is created without an explicit pick.
 *  - syncSquadPlayers(): applies a new 23 to the match squad —
 *    inserts newly called-up players from templates and flags the
 *    dropped ones with is_squad_member = false (rows are kept for
 *    match history).
 */
class NationalSquadService
{
    /** National-team player templates season. */
    public const TEMPLATE_SEASON = '2026';

    /** Squad size for every convocatoria. */
    public const SQUAD_SIZE = 23;

    /**
     * The FIFA window the convocatoria applies to: the break we're
     * inside right now, otherwise the next upcoming one (7-day prompt
     * first, then a 60-day lookahead so creation-time picks count).
     *
     * @return array{start: string, end: string, label: string}|null
     */
    public static function relevantWindow(Game $game): ?array
    {
        $season = $game->season ?? self::TEMPLATE_SEASON;
        $today = ($game->current_date ?? now())->format('Y-m-d');

        return FifaInternationalBreaks::currentWindow($season, $today)
            ?? FifaInternationalBreaks::upcomingWithin($season, $today, 7)
            ?? FifaInternationalBreaks::upcomingWithin($season, $today, 60);
    }

    /**
     * Players the user cannot call up: injured (injury_until >= the
     * window start) in ANY of their non-deleted saves — club and
     * national-team games alike. A torn ACL in October keeps the
     * player out of the January window, for example.
     *
     * Keys are template player_id strings (what the picker posts),
     * values the injury return date (Y-m-d) for display.
     *
     * @return array<string, string> [player_id => injury_until]
     */
    public static function injuredPlayersUntil(string|int $userId, string $fromDate): array
    {
        $rows = DB::table('game_players as gp')
            ->join('games as g', 'g.id', '=', 'gp.game_id')
            ->join('game_player_match_state as ms', 'ms.game_player_id', '=', 'gp.id')
            ->where('g.user_id', $userId)
            ->whereNull('g.deleting_at')
            ->where('ms.injury_until', '>=', $fromDate)
            ->groupBy('gp.player_id')
            ->select('gp.player_id', DB::raw('MAX(ms.injury_until) as injury_until'))
            ->get();

        $injured = [];
        foreach ($rows as $row) {
            $injured[(string) $row->player_id] = (string) $row->injury_until;
        }

        return $injured;
    }

    /**
     * Provisional 23: the highest-rated eligible players for the
     * nation, skipping injured ones. Used when a national game is
     * created without an explicit pick (the per-window picker is the
     * real convocatoria).
     *
     * @param  array<string> $excludePlayerIds template player_ids to skip (injured)
     * @return list<string>
     */
    public static function provisionalSquad(string $teamId, array $excludePlayerIds = []): array
    {
        // Exclude retired players (they're tracked in game_players, but for
        // provisional we check the templates joined with game state)
        $query = DB::table('game_player_templates as t')
            ->leftJoin('game_players as gp', function ($join) use ($teamId) {
                $join->on('gp.player_id', '=', 't.player_id')
                     ->where('gp.team_id', '=', $teamId);
            })
            ->where('t.season', self::TEMPLATE_SEASON)
            ->where('t.team_id', $teamId)
            ->where(function ($q) {
                $q->whereNull('gp.retired_from_national')
                  ->orWhere('gp.retired_from_national', false);
            })
            ->orderByDesc('t.overall_score')
            ->limit(self::SQUAD_SIZE);

        if ($excludePlayerIds !== []) {
            $query->whereNotIn('t.player_id', $excludePlayerIds);
        }

        return $query->pluck('t.player_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * Apply a new 23 to the game's match squad:
     *  - players in the 23 without a game row are inserted from the
     *    season templates (with a fresh match_state row);
     *  - players with a game row but outside the 23 are flagged
     *    is_squad_member = false (kept for history, invisible to the
     *    lineup engine);
     *  - kept players are re-flagged true.
     *
     * @param list<string> $playerIds template player_id strings, exactly 23
     */
    public static function syncSquadPlayers(Game $game, array $playerIds): void
    {
        $playerIds = array_values(array_unique(array_map('strval', $playerIds)));
        $gameId = $game->id;
        $teamId = $game->team_id;

        $existing = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->pluck('player_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $toAdd = array_values(array_diff($playerIds, $existing));

        if ($toAdd !== []) {
            $placeholders = implode(',', array_fill(0, count($toAdd), '?'));

            // Same column list as SetupTournamentGame::createQualifierPlayers.
            DB::insert(
                <<<'SQL'
                INSERT INTO game_players (
                    id, game_id, player_id,
                    transfermarkt_id, sofascore_id, fc26_id, name, date_of_birth, nationality, height, foot,
                    team_id, number, position, secondary_positions,
                    market_value, market_value_cents, contract_until, annual_wage, release_clause, durability,
                    overall_score,
                    potential, potential_low, potential_high, tier
                )
                SELECT
                    gen_random_uuid(), ?, t.player_id,
                    t.transfermarkt_id, t.sofascore_id, t.fc26_id, t.name, t.date_of_birth, t.nationality, t.height, t.foot,
                    t.team_id, t.number, t.position, t.secondary_positions,
                    t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.release_clause, t.durability,
                    t.overall_score,
                    t.potential, t.potential_low, t.potential_high, t.tier
                FROM game_player_templates t
                WHERE t.season = ? AND t.team_id = ? AND t.player_id IN ($placeholders)
                ON CONFLICT (game_id, player_id) DO NOTHING
                SQL,
                [$gameId, self::TEMPLATE_SEASON, $teamId, ...$toAdd]
            );

            DB::insert(
                <<<'SQL'
                INSERT INTO game_player_match_state (game_player_id, game_id, fitness, morale)
                SELECT gp.id, gp.game_id, t.fitness, t.morale
                FROM game_players gp
                JOIN game_player_templates t
                  ON t.player_id = gp.player_id
                 AND t.team_id = gp.team_id
                 AND t.season = ?
                WHERE gp.game_id = ? AND gp.team_id = ?
                ON CONFLICT (game_player_id) DO NOTHING
                SQL,
                [self::TEMPLATE_SEASON, $gameId, $teamId]
            );
        }

        // Dropped players stay in the DB (history) but leave the squad.
        GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->whereNotIn('player_id', $playerIds)
            ->update(['is_squad_member' => false]);

        GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->whereIn('player_id', $playerIds)
            ->update(['is_squad_member' => true]);
    }
}
