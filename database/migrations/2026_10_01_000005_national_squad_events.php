<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('national_squad_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->string('player_id'); // template player_id
            $table->string('event_type', 20); // 'injury' | 'resignation'
            $table->date('window_start'); // FIFA window this affects
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'window_start']);
            $table->index(['game_id', 'player_id']);
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->boolean('retired_from_national')->default(false)->after('is_squad_member');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('national_squad_events');
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('retired_from_national');
        });
    }
};
