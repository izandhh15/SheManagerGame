<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matchday pricing set by the manager (club mode): single ticket,
     * official shirt, merchandising and stadium bar drink prices (euros).
     * Null = game default.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('ticket_price')->nullable();
            $table->unsignedInteger('shirt_price')->nullable();
            $table->unsignedInteger('merch_price')->nullable();
            $table->unsignedInteger('bar_price')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['ticket_price', 'shirt_price', 'merch_price', 'bar_price']);
        });
    }
};
