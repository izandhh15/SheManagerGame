<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the women's Olympic football tournament (Los Angeles 2028) as a
 * playable national-team competition.
 *
 * v1 format: 12 teams, 3 groups of 4 (single round-robin). Top 2 per group
 * + the 2 best third-place teams advance to the quarter-finals, then
 * single-leg knockouts: QF → SF → 3rd place → Final. Runs through the
 * group_stage_cup handler exactly like WWCU27/WEURO.
 *
 * The tournament sits in the national-team calendar right after the
 * World Cup 2027 (see TournamentCreationService::nextCompetitionInSequence):
 * the two World Cup finalists go on to defend/chase Olympic gold.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('competitions')->updateOrInsert(
            ['id' => 'WOLYMP'],
            [
                'name' => 'Juegos Olímpicos 2028',
                'country' => 'IN',
                'flag' => null,
                'tier' => 1,
                'type' => 'league',
                'role' => 'league',
                'scope' => 'continental',
                'handler_type' => 'group_stage_cup',
                'season' => '2026',
            ]
        );
    }

    public function down(): void
    {
        DB::table('competitions')->where('id', 'WOLYMP')->delete();
    }
};
