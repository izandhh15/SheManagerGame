<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Severance payment plans (pago de la carta de libertad a plazos).
     *
     * When a player is released (unilateral or mutual termination), the user
     * can choose to pay the severance in monthly installments instead of a
     * lump sum. Each plan is processed on GameDateAdvanced.
     */
    public function up(): void
    {
        Schema::create('severance_payment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('game_player_id')->nullable()->constrained()->nullOnDelete();
            $table->string('player_name');
            $table->unsignedBigInteger('total_amount'); // cents, includes interest
            $table->unsignedBigInteger('amount_paid')->default(0); // cents
            $table->unsignedBigInteger('monthly_amount'); // cents
            $table->unsignedInteger('months_total');
            $table->unsignedInteger('months_paid')->default(0);
            $table->string('status')->default('active'); // active, completed
            $table->date('next_due_date');
            $table->timestamps();

            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('severance_payment_plans');
    }
};
