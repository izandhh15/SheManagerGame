<?php

namespace App\Modules\Season\Services;

use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Unexpected events for national-team mode.
 *
 * Two types of events that occur AFTER the convocatoria:
 *
 * 1. Post-callup injuries (15% per window): A called-up player gets injured
 *    between the squad announcement and the window start, forcing a
 *    last-minute replacement.
 *
 * 2. Resignations (3% per season): A player announces their retirement from
 *    international football. For Spain, this is extremely rare (it's illegal
 *    to refuse a call-up — you can lose your license).
 */
class NationalTeamEventService
{
    /** Probability of a post-callup injury per window (0-1). */
    public const INJURY_PROBABILITY = 0.15;

    /** Probability of a resignation per season (0-1). */
    public const RESIGNATION_PROBABILITY = 0.03;

    /**
     * Roll for post-callup injuries when the game date advances toward a window.
     * Called daily; only triggers once per window per game.
     *
     * @return array List of events created: [['player_name' => ..., 'player_id' => ...]]
     */
    public static function rollPostCallupInjuries(Game $game): array
    {
        $window = NationalSquadService::relevantWindow($game);
        if (!$window) {
            return [];
        }

        $windowStart = $window['start'];
        $today = ($game->current_date ?? now())->format('Y-m-d');

        // Only roll in the 7 days before the window
        $daysUntil = (strtotime($windowStart) - strtotime($today)) / 86400;
        if ($daysUntil < 0 || $daysUntil > 7) {
            return [];
        }

        // Don't roll twice for the same window
        $alreadyRolled = DB::table('national_squad_events')
            ->where('game_id', $game->id)
            ->where('window_start', $windowStart)
            ->where('event_type', 'injury')
            ->exists();
        if ($alreadyRolled) {
            return [];
        }

        // Get current squad (23 called-up players)
        $squad = DB::table('game_players')
            ->where('game_id', $game->id)
            ->where('is_squad_member', true)
            ->where('retired_from_national', false)
            ->select('id', 'player_id', 'name', 'position')
            ->get();

        if ($squad->isEmpty()) {
            return [];
        }

        $events = [];

        // Roll for injury: 15% chance of 1-2 injuries
        if (mt_rand(1, 100) <= (self::INJURY_PROBABILITY * 100)) {
            $numInjuries = mt_rand(1, 2);
            $victims = $squad->random(min($numInjuries, $squad->count()));

            foreach ($victims as $victim) {
                // Mark as injured in match_state
                $injuryUntil = date('Y-m-d', strtotime($windowStart . ' +14 days'));
                DB::table('game_player_match_state')
                    ->where('game_player_id', $victim->id)
                    ->update(['injury_until' => $injuryUntil]);

                // Record the event
                DB::table('national_squad_events')->insert([
                    'id' => (string) Str::uuid(),
                    'game_id' => $game->id,
                    'player_id' => $victim->player_id,
                    'event_type' => 'injury',
                    'window_start' => $windowStart,
                    'description' => "{$victim->name} se ha lesionado y es baja para esta ventana.",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $events[] = [
                    'player_name' => $victim->name,
                    'player_id' => $victim->player_id,
                    'position' => $victim->position,
                    'type' => 'injury',
                ];
            }
        }

        return $events;
    }

    /**
     * Roll for resignations at season start.
     * Spain: extremely rare (0.5%). Others: 3%.
     *
     * @return array List of events created
     */
    public static function rollResignations(Game $game): array
    {
        $team = $game->team;
        $isSpain = $team && str_contains(strtolower($team->name), 'españa');

        $probability = $isSpain ? 0.005 : self::RESIGNATION_PROBABILITY;

        if (mt_rand(1, 1000) > ($probability * 1000)) {
            return [];
        }

        // Pick a random veteran (30+) from the national pool
        $candidate = DB::table('game_player_templates')
            ->where('season', '2026')
            ->where('team_id', $game->team_id)
            ->whereRaw("date_of_birth <= CURRENT_DATE - INTERVAL '30 years'")
            ->inRandomOrder()
            ->first();

        if (!$candidate) {
            return [];
        }

        // Mark as retired from national team
        DB::table('game_players')
            ->where('game_id', $game->id)
            ->where('player_id', $candidate->player_id)
            ->update(['retired_from_national' => true]);

        $window = NationalSquadService::relevantWindow($game);
        DB::table('national_squad_events')->insert([
            'id' => (string) Str::uuid(),
            'game_id' => $game->id,
            'player_id' => $candidate->player_id,
            'event_type' => 'resignation',
            'window_start' => $window['start'] ?? now()->format('Y-m-d'),
            'description' => "{$candidate->name} ha anunciado su retirada de la selección.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [[
            'player_name' => $candidate->name,
            'player_id' => $candidate->player_id,
            'type' => 'resignation',
        ]];
    }

    /**
     * Get pending events for a game (injuries needing replacement, etc.)
     */
    public static function pendingEvents(Game $game): array
    {
        $window = NationalSquadService::relevantWindow($game);
        if (!$window) {
            return [];
        }

        return DB::table('national_squad_events')
            ->where('game_id', $game->id)
            ->where('window_start', $window['start'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
}
