<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cross-instance (federated) friendships. user_id is always the
        // LOCAL user involved; friend_id stays set for local friendships
        // and is NULL when the other side lives on the peer instance.
        // The remote party is described by the remote_* columns.
        Schema::table('friendships', function (Blueprint $table) {
            $table->boolean('is_federated')->default(false);
            $table->boolean('is_remote_sender')->default(false);
            $table->unsignedBigInteger('remote_user_id')->nullable();
            $table->string('remote_username', 255)->nullable();
            $table->string('remote_club', 255)->nullable();
            $table->string('remote_instance', 255)->nullable();
            $table->uuid('remote_request_uuid')->nullable();

            $table->index('is_federated');
            $table->index(['remote_username', 'remote_instance']);
        });

        // friend_id is NOT NULL in the original migration; federated rows
        // need it nullable. No doctrine/dbal in this project, so raw SQL.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE friendships ALTER COLUMN friend_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('friendships', function (Blueprint $table) {
            $table->dropIndex(['is_federated']);
            $table->dropIndex(['remote_username', 'remote_instance']);
            $table->dropColumn([
                'is_federated',
                'is_remote_sender',
                'remote_user_id',
                'remote_username',
                'remote_club',
                'remote_instance',
                'remote_request_uuid',
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            // Only safe when no federated rows remain.
            DB::statement('ALTER TABLE friendships ALTER COLUMN friend_id SET NOT NULL');
        }
    }
};
