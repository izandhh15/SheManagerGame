<?php
// TEMPORAL: aplicar 000044+000045 con guards. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

$out = [];
try {
    $jobs = [
        ['pre_match_press', 'effects_applied', '2026_10_03_000044_add_effects_applied_to_pre_match_press_table'],
        ['game_matches', 'mens_stadium_rental', '2026_10_03_000045_add_mens_stadium_rental_to_game_matches'],
    ];
    foreach ($jobs as [$table, $col, $name]) {
        if (!Schema::hasColumn($table, $col)) {
            Schema::table($table, fn(Blueprint $t) => $t->boolean($col)->default(false));
            $out[] = "$table.$col added";
        } else { $out[] = "$table.$col already exists, skipped"; }
        if (!DB::table('migrations')->where('migration', $name)->exists()) {
            DB::table('migrations')->insert(['migration' => $name, 'batch' => 5]);
            $out[] = "ledger: $name recorded";
        } else { $out[] = "ledger: $name already recorded"; }
    }
    $out[] = 'ledger_count=' . DB::table('migrations')->count();
    $out[] = 'final effects_applied=' . (Schema::hasColumn('pre_match_press', 'effects_applied') ? 1 : 0)
        . ' mens_stadium_rental=' . (Schema::hasColumn('game_matches', 'mens_stadium_rental') ? 1 : 0);
} catch (Throwable $e) {
    $out[] = 'ERROR: ' . substr($e->getMessage(), 0, 300);
}
echo implode("\n", $out) . "\n";
