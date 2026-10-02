<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pre-match press conferences: the manager answers 2-3 journalist
        // questions before a big match (derby, final, european night, direct
        // rival). One record per match — answers move squad morale and board
        // confidence through the existing pressure system.
        Schema::create('pre_match_press', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('match_id');
            $table->json('answers');
            $table->integer('morale_delta')->default(0);
            $table->integer('confidence_delta')->default(0);
            $table->timestamps();

            $table->unique(['game_id', 'match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_match_press');
    }
};
