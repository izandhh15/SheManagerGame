<?php
// TEMPORAL v3: pasos manuales del increment. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];
$conn = DB::connection();

try {
    $conn->beginTransaction();
    $out[] = 'begin OK';
} catch (Throwable $e) {
    $out[] = 'begin FAIL: ' . substr($e->getMessage(), 0, 200);
}

try {
    $row = $conn->table('cache')->where('key', 'dbg-k1')->lockForUpdate()->first();
    $out[] = 'select for update OK: ' . json_encode($row ? ['key' => $row->key, 'value' => $row->value] : null);
} catch (Throwable $e) {
    $out[] = 'select for update FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 250);
}

try {
    $n = $conn->table('cache')->where('key', 'dbg-k1')->update(['value' => 'i:1;']);
    $out[] = "update OK: $n";
} catch (Throwable $e) {
    $out[] = 'update FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 250);
}

try {
    $conn->commit();
    $out[] = 'commit OK';
} catch (Throwable $e) {
    $out[] = 'commit FAIL: ' . substr($e->getMessage(), 0, 250);
    try { $conn->rollBack(); $out[] = 'rollback OK'; } catch (Throwable $e2) { $out[] = 'rollback FAIL'; }
}

echo implode("\n", $out) . "\n";
