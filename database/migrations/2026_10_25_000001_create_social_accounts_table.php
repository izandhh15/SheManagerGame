<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SIN transacción: contra el pooler Neon (PgBouncer), una migración
     * con varias sentencias DDL en una sola transacción aborta con
     * 25P02 (visto en 0.5.20: el ALTER de la FK fallaba porque la
     * transacción ya estaba abortada). Con withinTransaction = false
     * cada sentencia corre en autocommit y el error real, si lo hay,
     * se ve tal cual.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        // La ejecución fallida de 0.5.20 pudo dejar la tabla a medias
        // (sin FK ni unique). Nunca ha tenido datos reales: se recrea
        // limpia para un estado determinista.
        Schema::dropIfExists('social_accounts');

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20); // google | discord
            $table->string('provider_id');
            $table->string('provider_email')->nullable();
            $table->string('avatar')->nullable();
            $table->text('token')->nullable(); // cifrado antes de guardar
            $table->text('refresh_token')->nullable(); // cifrado antes de guardar
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
