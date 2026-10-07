<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\Cache;
$out = [];
$t = microtime(true);
try {
    Cache::store('neon-database')->put('test-put-key', 'hello', 60);
    $out[] = sprintf('put: %.0fms OK', (microtime(true)-$t)*1000);
} catch (Throwable $e) {
    $out[] = 'put FAIL: ' . substr($e->getMessage(), 0, 150);
}
echo implode("\n", $out) . "\n";
