<?php
// TEMPORAL: aplicar 000043 (stadium_loans.last_billed_season) con guard. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

$out = [];
try {
    if (!Schema::hasColumn('stadium_loans', 'last_billed_season')) {
        Schema::table('stadium_loans', fn(Blueprint $t) => $t->integer('last_billed_season')->nullable());
        $out[] = 'stadium_loans.last_billed_season added';
    } else { $out[] = 'stadium_loans.last_billed_season already exists, skipped'; }

    $name = '2026_10_03_000043_add_last_billed_season_to_stadium_loans';
    if (!DB::table('migrations')->where('migration', $name)->exists()) {
        DB::table('migrations')->insert(['migration' => $name, 'batch' => 4]);
        $out[] = "ledger: $name recorded";
    } else { $out[] = "ledger: $name already recorded"; }
    $out[] = 'ledger_count=' . DB::table('migrations')->count();
    $out[] = 'final last_billed_season=' . (Schema::hasColumn('stadium_loans', 'last_billed_season') ? 1 : 0);
} catch (Throwable $e) {
    $out[] = 'ERROR: ' . substr($e->getMessage(), 0, 300);
}
echo implode("\n", $out) . "\n";
