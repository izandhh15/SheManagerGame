<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fictional journalists for the in-game social network ("X" clone).
        // Every name/handle is invented — no real people or media brands.
        Schema::create('game_journalists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->string('name');
            $table->string('handle');
            $table->string('specialty')->default('general'); // fichajes, cronicas, rumores...
            $table->integer('followers')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('game_id');
            $table->unique(['game_id', 'handle']);
            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
        });

        // Link social posts back to their journalist (fans have null).
        Schema::table('social_posts', function (Blueprint $table) {
            $table->foreignUuid('journalist_id')->nullable()->after('author_handle');
            $table->foreign('journalist_id')->references('id')->on('game_journalists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropForeign(['journalist_id']);
            $table->dropColumn('journalist_id');
        });

        Schema::dropIfExists('game_journalists');
    }
};
