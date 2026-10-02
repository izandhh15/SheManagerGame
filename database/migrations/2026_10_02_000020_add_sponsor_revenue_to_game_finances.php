<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            // Shirt-sponsor and ad-board income from active sponsor deals
            // (see SponsorService). Fixed annual fees, so projection and
            // settlement agree exactly.
            $table->bigInteger('projected_shirt_sponsor_revenue')->default(0)->after('projected_naming_rights_revenue');
            $table->bigInteger('projected_ad_board_revenue')->default(0)->after('projected_shirt_sponsor_revenue');
            $table->bigInteger('actual_shirt_sponsor_revenue')->default(0)->after('actual_naming_rights_revenue');
            $table->bigInteger('actual_ad_board_revenue')->default(0)->after('actual_shirt_sponsor_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->dropColumn([
                'projected_shirt_sponsor_revenue',
                'projected_ad_board_revenue',
                'actual_shirt_sponsor_revenue',
                'actual_ad_board_revenue',
            ]);
        });
    }
};
