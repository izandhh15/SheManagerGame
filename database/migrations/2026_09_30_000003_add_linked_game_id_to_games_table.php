<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asymmetric dual-mode link between saves. The CLUB game is the PRIMARY
     * (linked_game_id = null, counts against the 3-game limit); the NATIONAL
     * game is the SECONDARY (linked_game_id = primary game id, does NOT count
     * against the limit). The FK is nullOnDelete as a safety net — in
     * practice App\Http\Actions\DeleteGame removes the linked partner
     * explicitly, so a save never dangles.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignUuid('linked_game_id')->nullable()->constrained('games')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['linked_game_id']);
            $table->dropColumn('linked_game_id');
        });
    }
};
