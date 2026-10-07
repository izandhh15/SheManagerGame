<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\Cache;
$out = [];
$t = microtime(true);
try {
    $store = Cache::store('neon-database');
    $out[] = 'store class: ' . get_class($store->getStore());
    $out[] = sprintf('store obtained: %.0fms', (microtime(true)-$t)*1000);
} catch (Throwable $e) {
    $out[] = 'store FAIL: ' . substr($e->getMessage(), 0, 150);
}
$t = microtime(true);
try {
    $v = Cache::store('neon-database')->get('test-key-123');
    $out[] = sprintf('store get: %.0fms (%s)', (microtime(true)-$t)*1000, $v ? 'HIT' : 'MISS');
} catch (Throwable $e) {
    $out[] = 'get FAIL: ' . substr($e->getMessage(), 0, 150);
}
echo implode("\n", $out) . "\n";
