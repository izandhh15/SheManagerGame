<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * National-team call-ups change at every FIFA window, but the
     * game_player rows must survive (match history, stats). This flag
     * marks which rows belong to the CURRENT convocatoria; dropped
     * players keep their rows with is_squad_member = false so the
     * lineup engine (LineupService::getAvailablePlayers) ignores them.
     *
     * Referenced by SetupTournamentGame since 34db47f but the column
     * was never created — new national-team games crashed on setup.
     */
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->boolean('is_squad_member')->default(true)->after('tier');
            $table->index(['game_id', 'team_id', 'is_squad_member']);
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'team_id', 'is_squad_member']);
            $table->dropColumn('is_squad_member');
        });
    }
};
