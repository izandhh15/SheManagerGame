<?php
// TEMPORAL v4: probar SELECT sin FOR UPDATE. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];
$conn = DB::connection();
$conn->beginTransaction();
$out[] = 'begin OK';

try {
    $row = $conn->table('cache')->where('key', 'dbg-k1')->first();
    $out[] = 'select SIN lock OK: ' . ($row ? 'found' : 'null');
} catch (Throwable $e) {
    $out[] = 'select SIN lock FAIL: ' . substr($e->getMessage(), 0, 200);
}

try {
    $n = $conn->table('cache')->where('key', 'dbg-k1')->update(['value' => 'i:2;']);
    $out[] = "update tras select sin lock OK: $n";
} catch (Throwable $e) {
    $out[] = 'update tras select sin lock FAIL: ' . substr($e->getMessage(), 0, 200);
}

try { $conn->commit(); $out[] = 'commit OK'; }
catch (Throwable $e) { $out[] = 'commit FAIL'; try { $conn->rollBack(); } catch (Throwable) {} }

echo implode("\n", $out) . "\n";
