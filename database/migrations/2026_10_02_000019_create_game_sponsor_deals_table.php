<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sponsor deals for the two manager-chosen commercial slots: shirt
     * sponsor (camiseta) and ad-board sponsor (valla publicitaria). The
     * stadium naming-rights slot keeps living in game_stadium_naming_deals.
     *
     * One table holds the whole lifecycle per slot: pre-season/monthly
     * offers (`pending`), the accepted contract (`active`), and the trail of
     * expired/rejected rows (history). The sponsor pays a fixed recurring
     * annual fee for as long as the deal runs.
     */
    public function up(): void
    {
        Schema::create('game_sponsor_deals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('team_id');

            // shirt | ad_board
            $table->string('slot');

            // The sponsoring brand.
            $table->string('sponsor_name');

            // local | regional | nacional | internacional — the sponsor's
            // stature, resolved from division + league position when the
            // offer was minted.
            $table->string('tier');

            // Fixed annual fee (cents) the sponsor pays while active.
            $table->bigInteger('annual_value_cents');

            // 1–3 seasons, chosen at offer time.
            $table->smallInteger('contract_seasons');

            // pending | active | expired | rejected
            $table->string('status')->default('pending');

            // Incumbent renewals are free and skip no friction.
            $table->boolean('is_renewal')->default(false);

            // Season this offer was generated in.
            $table->integer('offered_season');

            // When the offer arrived (monthly cadence, not just pre-season).
            $table->timestamp('offered_at')->nullable();

            // Set when an offer is accepted; end = start + contract_seasons - 1.
            $table->integer('start_season')->nullable();
            $table->integer('end_season')->nullable();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams');

            $table->index(['game_id', 'team_id', 'slot', 'status']);
        });

        DB::statement('ALTER TABLE game_sponsor_deals ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sponsor_deals');
    }
};
