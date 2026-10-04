<?php
// TEMPORAL: copiar datos Ohio -> EU. ?tables=lista separada por comas
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
$tablesParam = $_GET['tables'] ?? '';
if (!$tablesParam) {
    // Listar tablas con conteo
    $tables = DB::connection('pgsql')->select("SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename NOT LIKE 'telescope%' ORDER BY tablename");
    foreach ($tables as $t) {
        $n = DB::connection('pgsql')->table($t->tablename)->count();
        $out[] = $t->tablename . ':' . $n;
    }
} else {
    $tables = explode(',', $tablesParam);
    foreach ($tables as $table) {
        $table = trim($table);
        if (!preg_match('/^[a-z_]+$/', $table)) continue;
        try {
            $rows = DB::connection('pgsql')->table($table)->get();
            $count = 0;
            foreach ($rows->chunk(500) as $chunk) {
                $data = array_map(fn($r) => (array)$r, $chunk->toArray());
                if (!empty($data)) {
                    DB::connection('newdb')->table($table)->insert($data);
                    $count += count($data);
                }
            }
            $out[] = "$table: $count";
        } catch (Throwable $e) {
            $out[] = "$table FAIL: " . substr($e->getMessage(), 0, 200);
        }
    }
    // Reset sequences
    foreach ($tables as $table) {
        $table = trim($table);
        try {
            DB::connection('newdb')->statement("SELECT setval(pg_get_serial_sequence('\"$table\"', 'id'), (SELECT MAX(id) FROM \"$table\")) WHERE EXISTS (SELECT 1 FROM \"$table\")");
        } catch (Throwable $e) { /* ignorar si no hay secuencia */ }
    }
    $out[] = 'sequences reset';
}

echo implode("\n", $out) . "\n";
