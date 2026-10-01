<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renewal_negotiations', function (Blueprint $table) {
            // Rival club offer pressuring the renewal (agent leverage)
            $table->uuid('rival_team_id')->nullable()->after('disposition');
            $table->bigInteger('rival_offer_wage')->nullable()->after('rival_team_id'); // cents
            $table->integer('rival_offer_years')->nullable()->after('rival_offer_wage');
            $table->boolean('rival_offer_active')->default(false)->after('rival_offer_years');
            // Agent patience 0-100: drops on every failed round, making counters harsher
            $table->integer('agent_patience')->default(100)->after('rival_offer_active');

            $table->foreign('rival_team_id')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('renewal_negotiations', function (Blueprint $table) {
            $table->dropForeign(['rival_team_id']);
            $table->dropColumn([
                'rival_team_id',
                'rival_offer_wage',
                'rival_offer_years',
                'rival_offer_active',
                'agent_patience',
            ]);
        });
    }
};
