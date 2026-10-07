<?php
// TEMPORAL: query minima.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
try {
    $n = DB::select('SELECT 1 as x');
    echo "OK: " . $n[0]->x . "\n";
} catch (Throwable $e) {
    echo "FAIL: " . substr($e->getMessage(), 0, 200) . "\n";
}
