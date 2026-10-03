<?php
// TEMPORAL v7: lockForUpdate en tabla users. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];

try {
    $r = DB::transaction(function () {
        $u = DB::table('users')->lockForUpdate()->first();
        return $u ? 'found user' : 'no users';
    });
    $out[] = "lockForUpdate en users OK: $r";
} catch (Throwable $e) {
    $out[] = 'lockForUpdate en users FAIL: ' . substr($e->getMessage(), 0, 250);
}

try {
    $r = DB::transaction(function () {
        $c = DB::table('cache')->lockForUpdate()->first();
        return $c ? 'found' : 'null';
    });
    $out[] = "lockForUpdate en cache (sin where) OK: $r";
} catch (Throwable $e) {
    $out[] = 'lockForUpdate en cache FAIL: ' . substr($e->getMessage(), 0, 250);
}

try {
    $r = DB::transaction(function () {
        $c = DB::table('cache')->where('key', 'no-existe-xyz')->lockForUpdate()->first();
        $n = DB::table('cache')->where('key', 'no-existe-xyz')->update(['value' => 'x']);
        return "select-null + update OK: $n";
    });
    $out[] = $r;
} catch (Throwable $e) {
    $out[] = 'select-null + update FAIL: ' . substr($e->getMessage(), 0, 250);
}

echo implode("\n", $out) . "\n";
