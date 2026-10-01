<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Training stage (concentración) configuration + federation budget for
     * national-team games. The stage is organized from the friendly
     * scheduler: destination, duration, intensity and focus, each with a
     * cost charged against the federation budget and effects applied to
     * the squad (fitness, morale, injury risk, youth development).
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->json('training_stage')->nullable();
            $table->unsignedBigInteger('federation_budget')->default(2000000);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['training_stage', 'federation_budget']);
        });
    }
};
