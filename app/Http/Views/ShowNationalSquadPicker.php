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
    public const POSITION_GROUPS = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];

    /**
     * Map the raw template position (e.g. 'Centre-Back') to the display
     * group used across the game (mirrors GamePlayer::getPositionGroupAttribute).
     */
    public static function positionGroup(string $position): string
    {
        return match ($position) {
            'Goalkeeper' => 'Goalkeeper',
            'Centre-Back', 'Left-Back', 'Right-Back', 'Defender' => 'Defender',
            'Defensive Midfield', 'Central Midfield', 'Attacking Midfield',
            'Left Midfield', 'Right Midfield', 'Midfielder' => 'Midfielder',
            'Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker',
            'Striker', 'Forward' => 'Forward',
            default => 'Midfielder',
        };
    }

    public function __invoke(Request $request, string $teamId)
    {
        $team = Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->findOrFail($teamId);

        // Dual-mode step 2: when ?club_id= is present and valid, the picker
        // posts to the dual endpoint (club_id + national_team_id + player_ids)
        // instead of the standalone national one.
        $dualClub = null;
        if ($request->query('club_id')) {
            $dualClub = Team::where('type', '!=', 'national')
                ->where('is_placeholder', false)
                ->find($request->query('club_id'));
        }

        $players = DB::table('game_player_templates')
            ->where('season', '2026')
            ->where('team_id', $teamId)
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

        $groupOrder = array_flip(self::POSITION_GROUPS);

        $players = $players->map(function ($row) use ($clubByPlayerId) {
            // Las plantillas de selecciones traen "2000-01-01" como fecha
            // comodín (sin fecha real): en ese caso no mostramos edad.
            $dob = $row->date_of_birth ? \Carbon\Carbon::parse($row->date_of_birth) : null;
            $isPlaceholderDob = $dob && $dob->format('Y-m-d') === '2000-01-01';

            return [
                'player_id' => $row->player_id,
                'name' => $row->name,
                'position' => $row->position,
                'group' => self::positionGroup($row->position ?? ''),
                'overall' => (int) $row->overall_score,
                'age' => ($dob && ! $isPlaceholderDob) ? $dob->diffInYears(now()) : null,
                'club' => $clubByPlayerId[$row->player_id] ?? null,
            ];
        })->sortBy([
            fn ($p) => $groupOrder[$p['group']] ?? 99,
            fn ($p) => -$p['overall'],
            fn ($p) => $p['name'],
        ])->values();

        // Limit to top 150 per position group to avoid rendering thousands of
        // players (e.g. Spain has 1,644). The user picks 23; the top 150 per
        // group is more than enough and keeps the page under ~1MB.
        $players = $players->groupBy('group')->flatMap(
            fn ($group) => $group->take(150)
        )->values();

        return view('national-squad-picker', [
            'team' => $team,
            'players' => $players,
            'clubs' => $players->pluck('club')->filter()->unique()->sort()->values(),
            'dualClub' => $dualClub,
        ]);
    }
}
