<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add support for custom pre-season trophies (e.g. Trofeo Joan Gamper,
     * Trofeo Santiago Bernabéu). A pre-season friendly can optionally be
     * designated as a trophy match with a custom name; the winner is recorded
     * in the manager's trophy cabinet.
     */
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->string('trophy_name')->nullable()->after('round_name');
        });

        Schema::table('manager_trophies', function (Blueprint $table) {
            // For custom trophies there's no competition row; store the name directly.
            $table->string('custom_name')->nullable()->after('competition_id');
        });

        // competition_id is required for standard trophies but custom trophies
        // don't reference a competition.
        Schema::table('manager_trophies', function (Blueprint $table) {
            $table->string('competition_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn('trophy_name');
        });

        Schema::table('manager_trophies', function (Blueprint $table) {
            $table->dropColumn('custom_name');
            $table->string('competition_id')->nullable(false)->change();
        });
    }
};
