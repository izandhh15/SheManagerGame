<?php
// TEMPORAL: ejecutar migraciones sin transacciones en nueva DB.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

$NEW_URL = 'postgresql://neondb_owner:npg_Egr1cvCpktK6@ep-wispy-cake-b1wth7xu-pooler.c-5.eu-central-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require';
$parts = parse_url($NEW_URL);
parse_str($parts['query'] ?? '', $q);
Config::set('database.connections.newdb', [
    'driver' => 'pgsql', 'host' => $parts['host'], 'port' => $parts['port'] ?? 5432,
    'database' => ltrim($parts['path'], '/'), 'username' => $parts['user'],
    'password' => $parts['pass'], 'sslmode' => $q['sslmode'] ?? 'require',
]);
Config::set('database.default', 'newdb');

$out = [];
$files = glob(database_path('migrations/*.php'));
sort($files);
$out[] = 'migrations found: ' . count($files);

$done = 0;
$errors = [];
foreach ($files as $file) {
    $name = basename($file, '.php');
    // Saltar si ya está en el ledger
    $exists = DB::connection('newdb')->table('migrations')->where('migration', $name)->exists();
    if ($exists) continue;
    
    try {
        $instance = require $file;
        $instance->up();
        DB::connection('newdb')->table('migrations')->insert([
            'migration' => $name, 'batch' => 1,
        ]);
        $done++;
    } catch (Throwable $e) {
        $errors[] = "$name: " . substr($e->getMessage(), 0, 150);
        if (count($errors) > 5) break;
    }
}

$out[] = "migrated: $done";
if (!empty($errors)) {
    $out[] = 'errors: ' . implode("\n", $errors);
}

echo implode("\n", $out) . "\n";
