<?php
// TEMPORAL v6: transaccion en tabla sessions. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];
$conn = DB::connection();

try {
    $result = $conn->transaction(function () use ($conn) {
        $row = $conn->table('cache')->where('key', 'dbg-k3')->first();
        return $row ? 'found' : 'null';
    });
    $out[] = "DB::transaction con select OK: $result";
} catch (Throwable $e) {
    $out[] = 'DB::transaction con select FAIL: ' . substr($e->getMessage(), 0, 200);
}

try {
    $result = $conn->transaction(function () use ($conn) {
        return $conn->table('cache')->where('key', 'dbg-k3')->update(['value' => 'i:5;']);
    });
    $out[] = "DB::transaction con update OK: $result";
} catch (Throwable $e) {
    $out[] = 'DB::transaction con update FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 250);
}

echo implode("\n", $out) . "\n";
