<?php
// TEMPORAL: copiar datos (v2).
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
$table = $_GET['table'] ?? 'users';

try {
    // Verificar que la tabla existe
    $exists = DB::connection('newdb')->select("SELECT 1 FROM pg_tables WHERE schemaname='public' AND tablename=?", [$table]);
    $out[] = "table $table exists: " . (count($exists) > 0 ? 'yes' : 'no');
    
    if (count($exists) > 0) {
        $rows = DB::connection('pgsql')->table($table)->limit(1)->get();
        $out[] = "old rows: " . DB::connection('pgsql')->table($table)->count();
        if (count($rows) > 0) {
            $data = (array)$rows[0];
            DB::connection('newdb')->table($table)->insert($data);
            $out[] = "insert 1 row OK";
        }
    }
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . substr($e->getMessage(), 0, 300);
}

echo implode("\n", $out) . "\n";
