<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preseason tour (gira de pretemporada) for club-mode games: the chosen
     * destination plus its economics (revenue multiplier, prestige points,
     * cost). Tour home friendlies (competition PRESEASON, except the family
     * derby) earn multiplied matchday revenue via RecordMatchdayRevenue.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->json('preseason_tour')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('preseason_tour');
        });
    }
};
