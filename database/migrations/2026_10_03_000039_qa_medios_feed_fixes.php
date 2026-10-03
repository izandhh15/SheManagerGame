<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA campaña «98 bugs» — fase 3 (medios), agente 15.
 *
 * - M15 (FEED-02): una sola declaración por (partida, partido):
 *   constraint único en press_statements(game_id, match_id).
 * - M18 (FEED-05): los posts del feed llevan la fecha del juego
 *   (`game_date`) para poder deduplicar oleadas de rumores con el reloj
 *   del juego en vez del reloj real.
 */
return new class extends Migration
{
    public function up(): void
    {
        // M15: el propio bug generó declaraciones duplicadas en producción;
        // limpiarlas (se conserva la más antigua de cada par) antes de añadir
        // el constraint único, o la migración fallaría.
        DB::statement(<<<'SQL'
            DELETE FROM press_statements ps
            USING press_statements ps2
            WHERE ps.match_id IS NOT NULL
              AND ps2.match_id IS NOT NULL
              AND ps.game_id = ps2.game_id
              AND ps.match_id = ps2.match_id
              AND (ps2.created_at, ps2.id) < (ps.created_at, ps.id)
            SQL);

        Schema::table('press_statements', function (Blueprint $table) {
            $table->unique(['game_id', 'match_id'], 'press_statements_game_match_unique');
        });

        Schema::table('social_posts', function (Blueprint $table) {
            $table->date('game_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('press_statements', function (Blueprint $table) {
            $table->dropUnique('press_statements_game_match_unique');
        });

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('game_date');
        });
    }
};
