<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QA campaña «98 bugs» — fase 3 (medios).
 *
 * M33: los `match_events` se borraban en el cierre de temporada sin
 * archivarse (se perdía quién marcó, asistencias, tarjetas). Se añade la
 * columna `match_events_archive` (JSON) a `season_archives` para guardar
 * el detalle de eventos antes del borrado. (Una versión binaria/gzip de
 * esta columna existió y fue eliminada en la migración
 * 2026_04_12_161959; el JSON evita los problemas de bytea en Postgres.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_archives', function (Blueprint $table) {
            $table->json('match_events_archive')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('season_archives', function (Blueprint $table) {
            $table->dropColumn('match_events_archive');
        });
    }
};
