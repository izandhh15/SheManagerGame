<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags academy "joyas" (jewels): standout 16-year-old prospects generated
 * with elite potential. The academy list UI shows them with a special badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_players', function (Blueprint $table) {
            $table->boolean('is_jewel')->default(false)->after('is_on_loan');
        });
    }

    public function down(): void
    {
        Schema::table('academy_players', function (Blueprint $table) {
            $table->dropColumn('is_jewel');
        });
    }
};
