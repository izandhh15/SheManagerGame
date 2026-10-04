<?php
// TEMPORAL: listar tablas nueva DB.
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

$tables = DB::connection('newdb')->select("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename");
echo implode("\n", array_map(fn($t) => $t->tablename, $tables)) . "\n";
