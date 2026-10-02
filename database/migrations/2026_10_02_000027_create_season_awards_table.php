<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable record of each season's awards gala (Gala de premios):
 * Balón de Oro, Pichichi, Zamora and MVP winners, computed from the
 * season's real simulated data by AwardsGalaProcessor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_awards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id')->index();
            $table->string('season', 16);
            // ballon_dor | pichichi | zamora | mvp
            $table->string('award_key', 32);
            $table->uuid('game_player_id');
            $table->uuid('team_id');
            // e.g. {"goals": 27} or {"goals_conceded_per_match": 0.68}
            $table->json('detail')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'season', 'award_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_awards');
    }
};
