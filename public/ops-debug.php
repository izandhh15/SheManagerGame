<?php
// TEMPORAL: verificar competiciones WQC en prod. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];
$ids = ['WQUEFA','WQAFC','WQCAF','WQCONC','WQCONM','WQOFC','WWCQ'];

try {
    $found = DB::table('competitions')->whereIn('id', $ids)->pluck('id')->toArray();
    $out[] = 'WQC found: ' . json_encode($found);
    $out[] = 'WQC missing: ' . json_encode(array_diff($ids, $found));
    
    $total = DB::table('competitions')->count();
    $out[] = "total competitions: $total";
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . substr($e->getMessage(), 0, 200);
}

echo implode("\n", $out) . "\n";
