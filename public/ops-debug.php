<?php
// TEMPORAL: migrar DB Ohio -> EU. Borrar tras usar.
// Uso: ?step=schema | ?step=count | ?step=copy&table=xxx
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

$NEW_URL = 'postgresql://neondb_owner:npg_Egr1cvCpktK6@ep-wispy-cake-b1wth7xu-pooler.c-5.eu-central-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require';

// Configurar conexion nueva
$parts = parse_url($NEW_URL);
parse_str($parts['query'] ?? '', $q);
Config::set('database.connections.newdb', [
    'driver' => 'pgsql',
    'host' => $parts['host'],
    'port' => $parts['port'] ?? 5432,
    'database' => ltrim($parts['path'], '/'),
    'username' => $parts['user'],
    'password' => $parts['pass'],
    'sslmode' => $q['sslmode'] ?? 'require',
]);

$step = $_GET['step'] ?? 'schema';
$out = [];

if ($step === 'schema') {
    // Ejecutar migraciones en la nueva DB
    Config::set('database.default', 'newdb');
    try {
        Artisan::call('migrate', ['--force' => true]);
        $out[] = 'migrate OK';
        $out[] = substr(Artisan::output(), 0, 1000);
    } catch (Throwable $e) {
        $out[] = 'migrate FAIL: ' . substr($e->getMessage(), 0, 500);
    }
} elseif ($step === 'count') {
    try {
        $oldTables = DB::connection('pgsql')->select("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename");
        $newTables = DB::connection('newdb')->select("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename");
        $out[] = 'old tables: ' . count($oldTables);
        $out[] = 'new tables: ' . count($newTables);
        $oldNames = array_column(array_map(fn($t) => (array)$t, $oldTables), 'tablename');
        $newNames = array_column(array_map(fn($t) => (array)$t, $newTables), 'tablename');
        $missing = array_diff($oldNames, $newNames);
        $out[] = 'missing in new: ' . json_encode(array_values($missing));
    } catch (Throwable $e) {
        $out[] = 'FAIL: ' . substr($e->getMessage(), 0, 300);
    }
} elseif ($step === 'copy') {
    $table = $_GET['table'] ?? '';
    if (!$table || !preg_match('/^[a-z_]+$/', $table)) {
        $out[] = 'invalid table';
    } else {
        try {
            // Desactivar FKs durante la copia
            DB::connection('newdb')->statement("SET session_replication_role = 'replica'");
            $rows = DB::connection('pgsql')->table($table)->get();
            $count = 0;
            foreach ($rows->chunk(500) as $chunk) {
                $data = array_map(fn($r) => (array)$r, $chunk->toArray());
                if (!empty($data)) {
                    DB::connection('newdb')->table($table)->insert($data);
                    $count += count($data);
                }
            }
            DB::connection('newdb')->statement("SET session_replication_role = 'origin'");
            $out[] = "table $table: $count rows copied";
        } catch (Throwable $e) {
            $out[] = "FAIL $table: " . substr($e->getMessage(), 0, 300);
        }
    }
}

echo implode("\n", $out) . "\n";
