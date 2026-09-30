<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the user-picked 23-player squad for national-team games
     * (WWCQ qualifiers, beta). The setup job reads it to materialise only
     * the called-up players; keeping it on the game makes setup retries
     * (Game::redispatchSetupJob) work without re-asking the user.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->json('national_squad_player_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('national_squad_player_ids');
        });
    }
};
