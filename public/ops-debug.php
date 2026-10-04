<?php
// TEMPORAL: contar tablas en ambas DBs.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$NEW_URL = 'postgresql://neondb_owner:npg_Egr1cvCpktK6@ep-wispy-cake-b1wth7xu-pooler.c-5.eu-central-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require';
$parts = parse_url($NEW_URL);
parse_str($parts['query'] ?? '', $q);
Config::set('database.connections.newdb', [
    'driver' => 'pgsql', 'host' => $parts['host'], 'port' => $parts['port'] ?? 5432,
    'database' => ltrim($parts['path'], '/'), 'username' => $parts['user'],
    'password' => $parts['pass'], 'sslmode' => $q['sslmode'] ?? 'require',
]);

$out = [];
try {
    $old = DB::connection('pgsql')->select("SELECT COUNT(*) as n FROM pg_tables WHERE schemaname='public'");
    $new = DB::connection('newdb')->select("SELECT COUNT(*) as n FROM pg_tables WHERE schemaname='public'");
    $out[] = 'old tables: ' . $old[0]->n;
    $out[] = 'new tables: ' . $new[0]->n;
    
    $oldMig = DB::connection('pgsql')->table('migrations')->count();
    $newMig = DB::connection('newdb')->table('migrations')->count();
    $out[] = "old ledger: $oldMig, new ledger: $newMig";
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . substr($e->getMessage(), 0, 200);
}

echo implode("\n", $out) . "\n";
