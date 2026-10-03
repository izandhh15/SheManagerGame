<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stadium_loans', function (Blueprint $table) {
            // Season (game season year) whose annual instalment was already
            // billed. billAnnualPayment() skips when it matches the current
            // season, making re-execution of the billing processor idempotent
            // (crash between charge and checkpoint, concurrent advance polls).
            $table->integer('last_billed_season')->nullable()->after('season_started');
        });
    }

    public function down(): void
    {
        Schema::table('stadium_loans', function (Blueprint $table) {
            $table->dropColumn('last_billed_season');
        });
    }
};
