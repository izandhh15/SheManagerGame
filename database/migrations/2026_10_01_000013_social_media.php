<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fake social media posts (the in-game "Twitter").
        Schema::create('social_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->string('author_name');
            $table->string('author_handle');
            $table->text('text');
            // -1 negative, 0 neutral, 1 positive (towards the manager)
            $table->tinyInteger('sentiment')->default(0);
            $table->integer('likes')->default(0);
            $table->string('context')->nullable(); // e.g. 'post_match', 'transfer', 'sacking_rumor'
            $table->uuid('match_id')->nullable();
            $table->timestamps();

            $table->index('game_id');
            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
        });

        // Board confidence in the manager (0-100). Low values trigger warnings/sacking.
        Schema::table('games', function (Blueprint $table) {
            $table->integer('board_confidence')->default(70);
        });

        // Press conference statements made by the manager.
        Schema::create('press_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('match_id')->nullable();
            $table->string('statement_key'); // e.g. 'praise_star', 'criticize_flop'
            $table->uuid('target_player_id')->nullable();
            $table->integer('sentiment_impact')->default(0);
            $table->timestamps();

            $table->index('game_id');
            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_statements');
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('board_confidence');
        });
        Schema::dropIfExists('social_posts');
    }
};
