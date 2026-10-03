<?php
// TEMPORAL v5: UPDATE sin transaccion. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];
$conn = DB::connection();

try {
    $row = $conn->table('cache')->where('key', 'dbg-k1')->first();
    $out[] = 'select OK: ' . ($row ? 'found' : 'null');
} catch (Throwable $e) {
    $out[] = 'select FAIL: ' . substr($e->getMessage(), 0, 200);
}

try {
    $n = $conn->table('cache')->where('key', 'dbg-k1')->update(['value' => 'i:9;']);
    $out[] = "update sin transaccion OK: $n";
} catch (Throwable $e) {
    $out[] = 'update sin transaccion FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 250);
}

try {
    $n = $conn->table('cache')->insert(['key' => 'dbg-k3', 'value' => 'i:0;', 'expiration' => time() + 60]);
    $out[] = "insert sin transaccion OK: $n";
} catch (Throwable $e) {
    $out[] = 'insert sin transaccion FAIL: ' . substr($e->getMessage(), 0, 200);
}

echo implode("\n", $out) . "\n";
