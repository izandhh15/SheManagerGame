<?php

namespace App\Http\Views;

use App\Models\Team;
use App\Modules\Season\Services\ClubFormService;
use App\Modules\Season\Services\NationalSquadService;
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
            'Centre-Back', 'Left-Back', 'Right-Back', 'Defender', 'Defence' => 'Defender',
            'Defensive Midfield', 'Central Midfield', 'Attacking Midfield',
            'Left Midfield', 'Right Midfield', 'Midfielder', 'Midfield' => 'Midfielder',
            'Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker',
            'Striker', 'Forward' => 'Forward',
            default => 'Midfielder',
        };
    }

    public function __invoke(Request $request, string $teamId)
    {
        // Update mode: /game/{gameId}/national-squad — teamId is actually the gameId.
        // The team is resolved from the game.
        $updateGame = null;
        $team = Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->find($teamId);

        if (!$team) {
            // Try as gameId for update mode.
            $updateGame = \App\Models\Game::where('id', $teamId)
                ->where('user_id', $request->user()->id)
                ->first();
            if ($updateGame?->team) {
                $team = $updateGame->team;
                $teamId = $team->id;
            }
        }

        if (!$team || $team->type === 'national' && $team->is_placeholder) {
            abort(404);
        }
        // Ensure it's a national team.
        if ($team->type !== 'national') {
            abort(404);
        }

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

        // Club form: at creation time show last completed season (2025);
        // in update mode show the current season scaled by progress.
        $clubSeason = $updateGame ? (string) ($updateGame->season ?? '2026') : '2025';
        $clubProgress = $updateGame
            ? ClubFormService::seasonProgress($updateGame->current_date?->format('Y-m-d'))
            : 1.0;

        $players = $players->map(function ($row) use ($clubByPlayerId, $clubSeason, $clubProgress) {
            // Las plantillas de selecciones traen "2000-01-01" como fecha
            // comodín (sin fecha real): en ese caso no mostramos edad.
            $dob = $row->date_of_birth ? \Carbon\Carbon::parse($row->date_of_birth) : null;
            $isPlaceholderDob = $dob && $dob->format('Y-m-d') === '2000-01-01';
            $group = self::positionGroup($row->position ?? '');

            return [
                'player_id' => $row->player_id,
                'name' => $row->name,
                'position' => $row->position,
                'group' => $group,
                'overall' => (int) $row->overall_score,
                'age' => ($dob && ! $isPlaceholderDob) ? (int) $dob->diffInYears(now()) : null,
                'club' => $clubByPlayerId[$row->player_id] ?? null,
                'club_form' => ClubFormService::statsFor(
                    (string) $row->player_id,
                    (int) $row->overall_score,
                    $group,
                    $clubSeason,
                    $clubProgress,
                ),
            ];
        })->sortBy([
            fn ($p) => $groupOrder[$p['group']] ?? 99,
            fn ($p) => -$p['overall'],
            fn ($p) => $p['name'],
        ])->values();

        // NOTE 01-10-2026: Izan asked for NO limit at all — every eligible
        // player must be visible/filterable (previously take(150) per group,
        // which also had a bug keeping the lowest-rated). The payload for
        // Spain is ~1,669 players (~350KB JSON), acceptable without trimming.

        // Injured players can't be called up until they recover: look up
        // active injuries in every save of the user (club and national —
        // injuries carry over to the national team). Keyed by template
        // player_id, valued with the return date for display.
        $season = $updateGame?->season ?? NationalSquadService::TEMPLATE_SEASON;
        $today = ($updateGame?->current_date ?? now())->format('Y-m-d');
        // Which break this call-up is for: the game's relevant window in
        // update mode, otherwise the next break on the calendar.
        $window = $updateGame
            ? NationalSquadService::relevantWindow($updateGame)
            : \App\Modules\Competition\Configs\FifaInternationalBreaks::upcomingWithin($season, $today, 60);
        $injured = NationalSquadService::injuredPlayersUntil(
            $request->user()->id,
            $window['start'] ?? $today,
        );

        // Stats: club season stats (from the dual club game, never invented)
        // and national-team caps/goals — only in update mode (existing game).
        $clubStats = [];
        $nationalStats = [];
        if ($updateGame) {
            $statsService = app(\App\Modules\NationalTeam\Services\NationalTeamStatsService::class);
            $playerIds = $players->pluck('player_id')->all();
            $clubStats = $statsService->clubStats($updateGame, $playerIds);

            // Map national stats (keyed by game_player_id) to player_id.
            $gpByPlayerId = \App\Models\GamePlayer::where('game_id', $updateGame->id)
                ->whereIn('player_id', $playerIds)
                ->pluck('id', 'player_id')
                ->all();
            $rawNational = $statsService->nationalStats($updateGame, $teamId);
            foreach ($gpByPlayerId as $pid => $gpId) {
                if (isset($rawNational[$gpId])) {
                    $nationalStats[$pid] = $rawNational[$gpId];
                }
            }
        }

        return view('national-squad-picker', [
            'team' => $team,
            'players' => $players,
            'clubs' => $players->pluck('club')->filter()->unique()->sort()->values(),
            'dualClub' => $dualClub,
            'updateGame' => $updateGame,
            'injured' => $injured,
            'window' => $window,
            'clubStats' => $clubStats,
            'nationalStats' => $nationalStats,
        ]);
    }
}
