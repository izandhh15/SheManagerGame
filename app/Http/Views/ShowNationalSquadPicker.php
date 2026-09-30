<?php

namespace App\Http\Views;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Squad picker for national-team mode (beta, WWCQ qualifiers).
 *
 * Shows every player eligible for the chosen nation (2026 NT templates —
 * includes club players backfilled by nationality, and dual nationals
 * appear under each of their nations). The user picks exactly 23, then
 * InitNationalGame creates the game with that call-up.
 */
final class ShowNationalSquadPicker
{
    public function __invoke(Request $request, string $teamId)
    {
        $team = Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->findOrFail($teamId);

        $players = DB::table('game_player_templates')
            ->where('season', '2026')
            ->where('team_id', $teamId)
            ->orderByRaw(<<<'SQL'
                CASE position
                    WHEN 'GK' THEN 0
                    WHEN 'DEF' THEN 1
                    WHEN 'MID' THEN 2
                    WHEN 'FWD' THEN 3
                    ELSE 4
                END
            SQL)
            ->orderByDesc('overall_score')
            ->orderBy('name')
            ->get();

        // Club for each player (from their club template, if any) — one query.
        $playerIds = $players->pluck('player_id')->all();
        $clubByPlayerId = DB::table('game_player_templates as t')
            ->join('teams', 'teams.id', '=', 't.team_id')
            ->where('t.season', '2026')
            ->whereIn('t.player_id', $playerIds)
            ->where('teams.type', '!=', 'national')
            ->orderBy('t.player_id')
            ->pluck('teams.name', 't.player_id');

        $players = $players->map(fn ($row) => [
            'player_id' => $row->player_id,
            'name' => $row->name,
            'position' => $row->position,
            'overall' => (int) $row->overall_score,
            'age' => $row->date_of_birth
                ? now()->diffInYears(\Carbon\Carbon::parse($row->date_of_birth))
                : null,
            'club' => $clubByPlayerId[$row->player_id] ?? null,
        ]);

        return view('national-squad-picker', [
            'team' => $team,
            'players' => $players,
        ]);
    }
}
