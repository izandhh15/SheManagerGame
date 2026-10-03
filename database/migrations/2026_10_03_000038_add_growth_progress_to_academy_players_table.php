<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accumulates fractional academy growth between matchdays (QA bug A3).
 *
 * Per-matchday growth (0.07–0.13 points) was rounded with round() on every
 * matchday without accumulating fractions, so academy players never grew.
 * The fractional part now accumulates here; whole points move to
 * overall_score once the accumulated progress crosses 1.0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_players', function (Blueprint $table) {
            $table->float('growth_progress')->default(0)->after('overall_score');
        });
    }

    public function down(): void
    {
        Schema::table('academy_players', function (Blueprint $table) {
            $table->dropColumn('growth_progress');
        });
    }
};
