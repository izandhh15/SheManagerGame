<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FIFA confederation of national teams (UEFA, AFC, CAF, CONCACAF,
     * CONMEBOL, OFC), backfilled by app:seed-national-teams from NAT.json.
     * Nullable by design: teams seeded before this field existed (or whose
     * source data lacks it) keep null and fall back to the legacy global
     * qualifier draw instead of a confederation group.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('confederation', 20)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('confederation');
        });
    }
};
