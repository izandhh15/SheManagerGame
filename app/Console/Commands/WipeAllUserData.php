<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// TEMPORAL 25-10-2026, orden directa de Izan ("si borra todo"):
// borra TODAS las cuentas de usuario y TODOS sus datos de partida
// en producción. Se ejecuta UNA vez vía job post-deployment y luego
// SE QUITA (comando + job) en la versión siguiente.
// NO toca los datos de referencia del juego (equipos, jugadoras,
// competiciones, plantillas): sin ellos el juego no podría volver a
// jugarse con cuentas nuevas. Si Izan quiere borrar también eso,
// es otro comando aparte.
// SIN transacción a propósito (Neon pooler): TRUNCATE es una sola
// sentencia y ya es atómica por sí misma.
class WipeAllUserData extends Command
{
    protected $signature = 'app:wipe-all-user-data';

    protected $description = 'TEMPORAL: delete ALL user accounts and ALL their game data (production wipe, ordered by Izan 25-10-2026)';

    public function handle(): int
    {
        $tables = [
            // Cuentas y sesiones
            'users', 'password_reset_tokens', 'sessions', 'device_sessions', 'waitlist',
            // Partidas y todo lo que cuelga de ellas
            'games', 'matches', 'standings', 'game_matches', 'match_events', 'match_attendances',
            'game_standings', 'game_players', 'game_player_match_state', 'game_player_match_ratings',
            'competition_entries', 'cup_ties', 'game_finances', 'game_investments', 'game_journalists',
            'game_notifications', 'game_stadiums', 'game_stadium_naming_deals', 'game_stadium_projects',
            'game_tactics', 'game_tactical_presets', 'game_transfers', 'loans', 'budget_loans',
            'stadium_loans', 'financial_transactions', 'preseason_invitations', 'pre_match_press',
            'press_statements', 'social_posts', 'scout_reports', 'shortlisted_players',
            'transfer_listings', 'transfer_offers', 'player_suspensions', 'season_archives',
            'season_awards', 'season_ticket_pricings', 'tournament_summaries', 'simulated_seasons',
            'national_squad_events', 'manager_job_histories', 'manager_job_offers',
            'manager_season_records', 'manager_stats', 'manager_trophies',
            'user_squad_career_records', 'career_history', 'activation_events', 'friendships',
            'mutual_termination_negotiations', 'renewal_negotiations', 'severance_payment_plans',
            'academy_players',
        ];

        $existing = [];
        $usersBefore = 0;
        $gamesBefore = 0;

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->line("  skip (no existe): {$table}");
                continue;
            }
            $count = DB::table($table)->count();
            $this->line("  {$table}: {$count} filas");
            if ($table === 'users') {
                $usersBefore = $count;
            }
            if ($table === 'games') {
                $gamesBefore = $count;
            }
            $existing[] = "\"{$table}\"";
        }

        $this->info("Usuarios antes: {$usersBefore} | Partidas antes: {$gamesBefore}");
        $this->info('Truncando ' . count($existing) . ' tablas (CASCADE)...');

        DB::statement('TRUNCATE TABLE ' . implode(', ', $existing) . ' RESTART IDENTITY CASCADE');

        $usersAfter = Schema::hasTable('users') ? DB::table('users')->count() : 0;
        $gamesAfter = Schema::hasTable('games') ? DB::table('games')->count() : 0;
        $this->info("Usuarios después: {$usersAfter} | Partidas después: {$gamesAfter}");

        if ($usersAfter !== 0 || $gamesAfter !== 0) {
            $this->error('El borrado NO dejó users/games a 0.');
            return self::FAILURE;
        }

        $this->info('Borrado completo: 0 cuentas, 0 partidas.');
        return self::SUCCESS;
    }
}
