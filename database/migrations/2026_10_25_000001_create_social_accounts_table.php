<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
