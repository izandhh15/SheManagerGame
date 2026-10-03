<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Friend requests between users. A row is directional (user_id sent
        // the request to friend_id); status flips to 'accepted' when the
        // recipient accepts. Removing a friendship deletes the row.
        Schema::create('friendships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('friend_id')->constrained('users')->onDelete('cascade');
            $table->string('status', 20)->default('pending'); // pending | accepted
            $table->timestamps();

            $table->unique(['user_id', 'friend_id']);
            $table->index('friend_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
