<?php
// TEMPORAL: ver tamaño de sesiones. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];

try {
    $stats = DB::select("SELECT COUNT(*) as n, AVG(LENGTH(payload)) as avg_len, MAX(LENGTH(payload)) as max_len FROM sessions");
    $out[] = 'sessions: ' . $stats[0]->n . ', avg payload: ' . round($stats[0]->avg_len) . ' bytes, max: ' . round($stats[0]->max_len) . ' bytes';
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . substr($e->getMessage(), 0, 200);
}

echo implode("\n", $out) . "\n";
