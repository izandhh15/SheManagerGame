<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
$out = [];
$t = microtime(true);
try {
    $v = Cache::get('career_mode_countries:v6');
    $out[] = sprintf('cache get: %.0fms (%s)', (microtime(true)-$t)*1000, $v ? 'HIT' : 'MISS');
} catch (Throwable $e) {
    $out[] = 'cache FAIL: ' . substr($e->getMessage(), 0, 150);
}
$t = microtime(true);
try {
    $n = DB::table('competitions')->count();
    $out[] = sprintf('count competitions: %.0fms (n=%d)', (microtime(true)-$t)*1000, $n);
} catch (Throwable $e) {
    $out[] = 'count FAIL: ' . substr($e->getMessage(), 0, 150);
}
echo implode("\n", $out) . "\n";
