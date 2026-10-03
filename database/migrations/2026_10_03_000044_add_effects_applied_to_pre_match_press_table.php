<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_match_press', function (Blueprint $table) {
            // Crash-safety flag for PressConferenceService::answer(): the
            // morale/board-confidence effects are applied right after the
            // insert, so a crash (or a concurrent double submit) between the
            // two used to leave a row whose effects never applied — and the
            // idempotency check then returned it without ever applying them.
            $table->boolean('effects_applied')->default(false)->after('confidence_delta');
        });
    }

    public function down(): void
    {
        Schema::table('pre_match_press', function (Blueprint $table) {
            $table->dropColumn('effects_applied');
        });
    }
};
