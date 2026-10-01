<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data fix: 82 team renames from the 01-10-2026 audit (Soccerdonna as source
 * of truth, Izan's explicit corrections). High-confidence renames only.
 *
 * Idempotent: only updates where the old name still exists and the new
 * name doesn't (avoids duplicates).
 */
return new class extends Migration
{
    private const RENAMES = [
        ['Deportivo Abanca', 'Dépor Abanca'],
        ['VCF Femenino', 'Valencia CF Femenino'],
        ['Costa Adeje Tenerife', 'Costa Adeje Tenerife Egatesa'],
        ['Athletic Bilbao II', 'Athletic Club B'],
        ['Atlético Madrid B', 'Atlético de Madrid B'],
        ['Real Sociedad San Sebastián B', 'Real Sociedad B'],
        ['Real Unión de Tenerife', 'Real Unión de Tenerife Santa Cruz'],
        ['Sport Extremadura', 'Sportextremadura CD'],
        ['FC Barcelona II', 'FC Barcelona B'],
        ['RC Deportivo A Coruña B', 'Dépor ABANCA B'],
        ['Real Avilés', 'Real Avilés CF'],
        ['Espanyol Barcelona B', 'RCD Espanyol B'],
        ['Atlético Baleares', 'Balears FC'],
        ['Prainsa', 'Zaragoza CFF'],
        ['UD Levante B', 'Levante UD B'],
        ["Valencia CF 'B'", 'Valencia CF B'],
        ['FC Ona Sant Adria', 'FC Ona Sant Adrià'],
        ['SE AEM Lleida', 'SE AEM'],
        ['Club Atlético Málaga', 'Málaga CF'],
        ['CF Pozuelo', 'CF Pozuelo de Alarcón'],
        ['UD Almeria', 'UD Almería'],
        ['Inter Mailand', 'Inter'],
        ['ACF Mailand', 'AC Milan'],
        ['ACF Florenz', 'ACF Fiorentina'],
        ['AS Rom', 'AS Roma'],
        ['ACF Brescia', 'ACF Brescia Femminile'],
        ['Bologna FC 1909', 'ASD Bologna FC 1909'],
        ['Cesena FC', 'Cesena FC Femminile'],
        ['Frosinone Calcio', 'Frosinone Calcio Femminile'],
        ['Venezia FC', 'Venezia FC Femminile'],
        ['Rasenballsport Leipzig', 'RB Leipzig'],
        ['1. FC Nuremberg', '1. FC Nürnberg'],
        ['1899 Hoffenheim', 'TSG 1899 Hoffenheim'],
        ['Bayer Leverkusen', 'Bayer 04 Leverkusen'],
        ['CS Chenois', 'Servette FC Chênois Féminin'],
        ['Oud-Heverlee Leuven', 'OH Leuven Women'],
        ['Slavia Praha', 'SK Slavia Praha'],
        ['RSC Anderlecht', 'RSCA Women'],
        ['FC Brügge', 'Club YLA'],
        ['BSC Young Boys', 'BSC YB Frauen'],
        ['Sparta Praha', 'AC Sparta Praha'],
        ['AGF Kvindefodbold', 'Aarhus GF'],
        ['Lillestrøm SK', 'Lillestrøm SK Kvinner'],
        ['Standard Liège', 'Standard Femina'],
        ['Lyn Oslo', 'Lyn Fotball'],
        ['Stabæk FK', 'Stabæk Fotball'],
        ['Grasshoppers Zürich', 'Grasshopper Club Zürich'],
        ['F.C. Barcelona', 'FC Barcelona'],
        ['Club Atlético de Madrid', 'Atlético de Madrid'],
        ['Servette FC', 'Servette FC Chênois Féminin'],
        ['Gallos Blancos de Querétaro', 'Querétaro FC'],
        ['Xolos Tijuana', 'Club Tijuana'],
        ['Atlas Guadalajara', 'Atlas FC'],
        ['Rayados de Monterrey', 'CF Monterrey'],
        ['Club Deportivo Toluca', 'Deportivo Toluca FC'],
        ['Alianza', 'Alianza Women FC'],
        ['Saprissa', 'Deportivo Saprissa'],
        ['Vancouver Rise', 'Vancouver Rise FC'],
        ['Nacional', 'Club Nacional de Football'],
        ['Olimpia', 'Club Olimpia'],
        ['Mixto', 'Mixto Esporte Clube'],
        ['Vitória', 'Esporte Clube Vitória'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as [$old, $new]) {
            // Skip if the new name already exists (avoid duplicates)
            $newExists = DB::table('teams')->where('name', $new)->exists();
            if ($newExists) {
                continue;
            }

            DB::table('teams')
                ->where('name', $old)
                ->update(['name' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Not reversible - names are authoritative
    }
};
