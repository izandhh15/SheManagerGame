<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the (real-world) head coach / manager name to teams.
 *
 * Used by the affiliate career mode: when the board sacks the first-team
 * coach after a poor season, the news names them. Populated separately
 * with verified real names; null means "unknown yet".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('manager_name', 120)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('manager_name');
        });
    }
};
