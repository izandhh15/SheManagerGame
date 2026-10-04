<?php
// TEMPORAL: debug conexion newdb.
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
    $db = DB::connection('newdb')->select('SELECT current_database() as db, current_schema() as schema');
    $out[] = 'db: ' . $db[0]->db . ', schema: ' . $db[0]->schema;
    
    $tables = DB::connection('newdb')->select("SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename='users'");
    $out[] = 'users in pg_tables: ' . count($tables);
    
    $search = DB::connection('newdb')->select("SHOW search_path");
    $out[] = 'search_path: ' . json_encode($search);
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . substr($e->getMessage(), 0, 200);
}

echo implode("\n", $out) . "\n";
