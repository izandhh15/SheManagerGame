<?php
// TEMPORAL: ejecutar migraciones pendientes (000039/000041/000042). Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

$out = [];
try {
    $code = Artisan::call('migrate', ['--force' => true]);
    $out[] = 'migrate EXIT: ' . $code;
    $out[] = Artisan::output();
    $out[] = 'ledger_count=' . DB::table('migrations')->count();
    $out[] = 'final game_date=' . (Schema::hasColumn('social_posts', 'game_date') ? 1 : 0)
        . ' game_player_id=' . (Schema::hasColumn('social_posts', 'game_player_id') ? 1 : 0)
        . ' archive_col=' . (Schema::hasColumn('season_archives', 'match_events_archive') ? 1 : 0);
} catch (Throwable $e) {
    $out[] = 'ERROR: ' . $e->getMessage();
}
echo implode("\n", $out) . "\n";
