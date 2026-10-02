<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the women's Club World Cup (CWC) as a playable club competition.
 *
 * v1 format: 32 teams, 8 groups of 4 (single round-robin). Top 2 per group
 * advance to the round of 16, then single-leg knockouts: R16 → QF → SF →
 * Final (no third-place match). Runs through the group_stage_cup handler
 * exactly like WWCU27/WEURO/WOLYMP.
 *
 * Qualification happens at season close
 * (ClubWorldCupQualificationProcessor): the best clubs of each
 * confederation by squad market value — 16 UEFA, 8 CONMEBOL, 8 CONCACAF.
 * The groups are drawn at the start of the new season
 * (ClubWorldCupInitProcessor) and played in July, opening the season.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('competitions')->updateOrInsert(
            ['id' => 'CWC'],
            [
                'name' => 'Mundial de Clubes',
                'country' => 'XX',
                'flag' => null,
                'tier' => 0,
                'type' => 'cup',
                'role' => 'european',
                'scope' => 'continental',
                'handler_type' => 'group_stage_cup',
                'season' => '2026',
            ]
        );
    }

    public function down(): void
    {
        DB::table('competitions')->where('id', 'CWC')->delete();
    }
};
