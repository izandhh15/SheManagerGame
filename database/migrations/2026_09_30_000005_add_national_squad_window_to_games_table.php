<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Start date of the FIFA window the national squad was last
            // confirmed for. The picker is prompted a few days before each
            // window; this avoids asking twice for the same one.
            $table->date('national_squad_window')->nullable()->after('national_squad_player_ids');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('national_squad_window');
        });
    }
};
