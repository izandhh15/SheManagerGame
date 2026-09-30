<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the stand-in flag to game_players. Stand-in players are fictional
     * filler players auto-generated on the user's reserve (filial) team when
     * call-ups to the first team would drop the reserve below its squad
     * minimum. They exist only so the reserve has enough bodies for the
     * simulation engine — the user never sees them, can't select them,
     * and they never appear on the transfer market.
     */
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->boolean('is_stand_in')->default(false)->after('tier');
            $table->index(['game_id', 'team_id', 'is_stand_in']);
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'team_id', 'is_stand_in']);
            $table->dropColumn('is_stand_in');
        });
    }
};
