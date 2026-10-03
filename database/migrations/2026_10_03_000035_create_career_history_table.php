<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Career snapshots taken when a game is deleted, so friends (and the
        // owner) keep a history of past careers. game_id has no FK on
        // purpose: the games row is deleted afterwards.
        Schema::create('career_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->uuid('game_id')->nullable();
            $table->string('team_name');
            $table->string('team_type', 20)->default('club'); // club | national
            $table->string('season')->nullable();
            $table->json('stats')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_history');
    }
};
