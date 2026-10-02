<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags affiliate-career ("Carrera con Filiales") saves where the first-team
 * board already sacked its coach MID-SEASON and handed the job to the user.
 *
 * Guards the season-end AffiliateFirstTeamSackProcessor so it never fires a
 * second time on the same career.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('affiliate_midseason_sack')->default(false)->after('pair_mode');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('affiliate_midseason_sack');
        });
    }
};
