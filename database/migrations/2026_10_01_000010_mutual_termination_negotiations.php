<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Negociaciones de rescisión de mutuo acuerdo.
     */
    public function up(): void
    {
        Schema::create('mutual_termination_negotiations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('game_player_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('open'); // open, agreed, rejected, walked_away, completed
            $table->unsignedTinyInteger('round')->default(0);
            $table->unsignedBigInteger('agent_demand')->nullable(); // cents
            $table->unsignedBigInteger('user_offer')->nullable(); // cents
            $table->unsignedBigInteger('agreed_amount')->nullable(); // cents

            $table->index(['game_id', 'status']);
            $table->index(['game_player_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutual_termination_negotiations');
    }
};
