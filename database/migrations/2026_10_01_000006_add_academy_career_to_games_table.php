<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Academy career mode: tracks the original first-team club when a
     * manager starts at the lowest filial (e.g. Barça C) and works up.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->uuid('academy_career_club_id')->nullable()->after('team_id');
            $table->foreign('academy_career_club_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['academy_career_club_id']);
            $table->dropColumn('academy_career_club_id');
        });
    }
};
