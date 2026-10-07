<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$out = [];
$t = microtime(true);
try {
    $n = DB::table('cache')->count();
    $out[] = sprintf('cache count: %.0fms (n=%d)', (microtime(true)-$t)*1000, $n);
} catch (Throwable $e) {
    $out[] = 'count FAIL: ' . substr($e->getMessage(), 0, 150);
}
$t = microtime(true);
try {
    $r = DB::table('cache')->where('key', 'test')->first();
    $out[] = sprintf('cache select: %.0fms', (microtime(true)-$t)*1000);
} catch (Throwable $e) {
    $out[] = 'select FAIL: ' . substr($e->getMessage(), 0, 150);
}
echo implode("\n", $out) . "\n";
