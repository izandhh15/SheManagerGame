<?php
// TEMPORAL: migración completa Ohio -> EU en una sola llamada.
set_time_limit(600);
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
// Tablas con datos (excluyendo cache, sessions, telescope, migrations)
$tables = ['users','competitions','teams','competition_teams','club_profiles','games','game_stadiums','game_tactics','game_player_templates','activation_events','device_sessions','traffic_daily','traffic_hourly','traffic_visitor_days','visitor_heartbeats'];

foreach ($tables as $table) {
    try {
        $count = DB::connection('pgsql')->table($table)->count();
        if ($count == 0) {
            $out[] = "$table: 0 (skip)";
            continue;
        }
        // Vaciar destino por si acaso
        DB::connection('newdb')->table($table)->delete();
        $copied = 0;
        DB::connection('pgsql')->table($table)->orderBy('id')->chunk(500, function($rows) use ($table, &$copied) {
            $data = array_map(fn($r) => (array)$r, $rows->toArray());
            DB::connection('newdb')->table($table)->insert($data);
            $copied += count($data);
        });
        $out[] = "$table: $copied/$count OK";
    } catch (Throwable $e) {
        $out[] = "$table FAIL: " . substr($e->getMessage(), 0, 150);
    }
}

// Reset sequences
foreach ($tables as $table) {
    try {
        DB::connection('newdb')->statement("SELECT setval(pg_get_serial_sequence('\"$table\"', 'id'), GREATEST((SELECT MAX(id) FROM \"$table\"), 1))");
    } catch (Throwable $e) {}
}
$out[] = 'done';

echo implode("\n", $out) . "\n";
