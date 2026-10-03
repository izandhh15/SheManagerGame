<?php
// TEMPORAL v2: aislar que operacion de cache falla. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$out = [];

try {
    $r = Cache::add('dbg-k1', 0, 60);
    $out[] = 'Cache::add OK: ' . var_export($r, true);
} catch (Throwable $e) {
    $out[] = 'Cache::add FAIL: ' . substr($e->getMessage(), 0, 250);
}

try {
    $r = Cache::increment('dbg-k1');
    $out[] = 'Cache::increment OK: ' . var_export($r, true);
} catch (Throwable $e) {
    $out[] = 'Cache::increment FAIL: ' . substr($e->getMessage(), 0, 250);
}

try {
    $cols = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name='cache' ORDER BY ordinal_position");
    $out[] = 'cache cols: ' . json_encode(array_map(fn($c) => $c->column_name . ':' . $c->data_type, $cols));
} catch (Throwable $e) {
    $out[] = 'cols FAIL: ' . substr($e->getMessage(), 0, 200);
}

try {
    $r = DB::table('cache')->insertOrIgnore(['key' => 'dbg-k2', 'value' => 'x', 'expiration' => time() + 60]);
    $out[] = 'insertOrIgnore OK: ' . var_export($r, true);
} catch (Throwable $e) {
    $out[] = 'insertOrIgnore FAIL: ' . substr($e->getMessage(), 0, 250);
}

echo implode("\n", $out) . "\n";
