<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\Cache;
echo "default: " . config('cache.default') . "\n";
echo "CACHE_STORE env: " . env('CACHE_STORE', '(not set)') . "\n";
$t = microtime(true);
try {
    $v = Cache::get('test-default-key');
    echo sprintf('Cache::get default: %.0fms (%s)', (microtime(true)-$t)*1000, $v ? 'HIT' : 'MISS') . "\n";
} catch (Throwable $e) {
    echo 'FAIL: ' . substr($e->getMessage(), 0, 150) . "\n";
}
