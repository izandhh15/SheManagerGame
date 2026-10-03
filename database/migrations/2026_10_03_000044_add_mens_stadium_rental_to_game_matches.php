<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            // Flags matches moved to a men's/municipal ground via
            // MensStadiumRequestService::confirmQuote(). Neutral cup finals
            // (CupDrawService) also set neutral_venue_name, so the rental
            // quota (MAX_PER_SEASON) must count THIS flag — not every
            // neutral-venue match — or finals would eat the quota.
            $table->boolean('mens_stadium_rental')->default(false)->after('neutral_venue_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn('mens_stadium_rental');
        });
    }
};
