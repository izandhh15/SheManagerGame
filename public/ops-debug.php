<?php
// TEMPORAL: perfilar homepage. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

$out = [];
$t0 = microtime(true);

// 1. Session read (simulado)
$t = microtime(true);
try {
    $s = DB::table('sessions')->where('id', 'test')->first();
    $out[] = sprintf('session read: %.0f ms', (microtime(true)-$t)*1000);
} catch (Throwable $e) { $out[] = 'session read FAIL'; }

// 2. Cache read (landing-stats)
$t = microtime(true);
try {
    $v = Cache::get('landing-stats');
    $out[] = sprintf('cache get (landing-stats): %.0f ms %s', (microtime(true)-$t)*1000, $v ? '(hit)' : '(miss)');
} catch (Throwable $e) { $out[] = 'cache get FAIL'; }

// 3. Simple query
$t = microtime(true);
try {
    $c = DB::table('competitions')->count();
    $out[] = sprintf('simple count query: %.0f ms', (microtime(true)-$t)*1000);
} catch (Throwable $e) { $out[] = 'count FAIL'; }

// 4. TrackVisitors upsert (simulado)
$t = microtime(true);
try {
    DB::table('visits')->updateOrInsert(['id' => 1], ['updated_at' => now()]);
    $out[] = sprintf('upsert: %.0f ms', (microtime(true)-$t)*1000);
} catch (Throwable $e) { $out[] = 'upsert FAIL: ' . substr($e->getMessage(), 0, 100); }

$out[] = sprintf('TOTAL: %.0f ms', (microtime(true)-$t0)*1000);

echo implode("\n", $out) . "\n";
