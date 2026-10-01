<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pre-season invitations from AI clubs + custom stadium selection.
     *
     * - preseason_invitations: AI clubs invite the user's team to friendlies
     *   (optionally trophy matches like Joan Gamper). The user accepts/declines.
     * - game_matches.stadium_name: optional custom stadium for a friendly
     *   (e.g. play the Trofeo Joan Gamper at Camp Nou).
     */
    public function up(): void
    {
        Schema::create('preseason_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('inviting_team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedTinyInteger('slot'); // 0-3, which pre-season slot
            $table->string('trophy_name')->nullable();
            $table->string('stadium_name')->nullable();
            $table->string('status')->default('pending'); // pending, accepted, declined
            $table->timestamps();

            $table->unique(['game_id', 'inviting_team_id', 'slot']);
            $table->index(['game_id', 'status']);
        });

        Schema::table('game_matches', function (Blueprint $table) {
            $table->string('stadium_name')->nullable()->after('trophy_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preseason_invitations');

        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn('stadium_name');
        });
    }
};
