<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Let the manager reply to hater posts with a predefined response.
        Schema::table('social_posts', function (Blueprint $table) {
            $table->string('manager_reply_key')->nullable()->after('match_id');
            $table->text('manager_reply_text')->nullable()->after('manager_reply_key');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn(['manager_reply_key', 'manager_reply_text']);
        });
    }
};
