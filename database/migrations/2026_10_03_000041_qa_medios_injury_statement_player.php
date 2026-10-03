<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M20 (QA medios): el parte médico de prensa desbloqueaba la lesión de otra
 * jugadora porque buscaba el comunicado con LIKE '%nombre%' ("Ana" casaba
 * con el parte de "Ana María").
 *
 * Los comunicados oficiales de lesión guardan ahora la referencia exacta a
 * la jugadora (game_player_id) y la comprobación usa match exacto por id.
 * Los comunicados publicados antes de este cambio se siguen reconociendo
 * por texto, pero con el nombre anclado al verbo de la plantilla
 * ("estará"/"estarà"/"will be"), que no casa con nombres más largos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->uuid('game_player_id')->nullable()->after('game_id');
            $table->index('game_player_id');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropIndex(['game_player_id']);
            $table->dropColumn('game_player_id');
        });
    }
};
