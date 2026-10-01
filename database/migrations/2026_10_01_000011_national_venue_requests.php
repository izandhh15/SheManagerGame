<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Venue organization for national-team friendlies.
     *
     * When the user schedules a national-team friendly at a club's stadium
     * (women's home ground) or at a men's big stadium, the owning club must
     * accept the request. While waiting for the club's answer the match keeps
     * a provisional neutral venue so it is always playable; on accept the
     * real stadium is written in, on reject it stays neutral (fewer earnings).
     */
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->string('venue_status')->default('confirmed'); // confirmed|pending_club|rejected
            $table->foreignUuid('venue_request_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('venue_request_type')->nullable(); // club|mens
            $table->string('venue_request_excuse')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['venue_status', 'venue_request_team_id', 'venue_request_type', 'venue_request_excuse']);
        });
    }
};
