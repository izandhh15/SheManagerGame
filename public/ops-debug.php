<?php
// TEMPORAL: migración con orden FK correcto.
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
DB::connection('newdb')->statement('SET search_path TO public');

$out = [];

function copyTable($table, $orderBy = 'id') {
    $copied = 0;
    $query = DB::connection('pgsql')->table($table);
    // Solo orderBy si la columna existe
    try {
        DB::connection('pgsql')->select("SELECT $orderBy FROM \"$table\" LIMIT 1");
        $query = $query->orderBy($orderBy);
    } catch (Throwable $e) {}
    
    DB::connection('newdb')->table($table)->delete();
    $query->chunk(500, function($rows) use ($table, &$copied) {
        $data = array_map(fn($r) => (array)$r, $rows->toArray());
        DB::connection('newdb')->table($table)->insert($data);
        $copied += count($data);
    });
    return $copied;
}

// Orden: sin dependencias primero
$tables = [
    'users', 'competitions', 'device_sessions',
    'traffic_daily', 'traffic_hourly', 'traffic_visitor_days', 'visitor_heartbeats',
];

foreach ($tables as $t) {
    try {
        $n = copyTable($t);
        $out[] = "$t: $n OK";
    } catch (Throwable $e) {
        $out[] = "$t FAIL: " . substr($e->getMessage(), 0, 120);
    }
}

// teams: copiar con parent_team_id NULL primero, luego actualizar
try {
    DB::connection('newdb')->table('teams')->delete();
    $copied = 0;
    DB::connection('pgsql')->table('teams')->orderBy('id')->chunk(500, function($rows) use (&$copied) {
        foreach ($rows as $r) {
            $data = (array)$r;
            $parent = $data['parent_team_id'] ?? null;
            $data['parent_team_id'] = null;
            DB::connection('newdb')->table('teams')->insert($data);
            $copied++;
        }
    });
    // Segunda pasada: actualizar parent_team_id
    $parents = DB::connection('pgsql')->table('teams')->whereNotNull('parent_team_id')->get(['id', 'parent_team_id']);
    foreach ($parents as $p) {
        DB::connection('newdb')->table('teams')->where('id', $p->id)->update(['parent_team_id' => $p->parent_team_id]);
    }
    $out[] = "teams: $copied OK";
} catch (Throwable $e) {
    $out[] = "teams FAIL: " . substr($e->getMessage(), 0, 120);
}

// Resto con dependencias
$rest = ['competition_teams', 'club_profiles', 'games', 'game_stadiums', 'game_tactics', 'game_player_templates', 'activation_events'];
foreach ($rest as $t) {
    try {
        $n = copyTable($t);
        $out[] = "$t: $n OK";
    } catch (Throwable $e) {
        $out[] = "$t FAIL: " . substr($e->getMessage(), 0, 120);
    }
}

// Reset sequences
foreach (array_merge($tables, ['teams'], $rest) as $t) {
    try {
        DB::connection('newdb')->statement("SELECT setval(pg_get_serial_sequence('\"$t\"', 'id'), GREATEST((SELECT MAX(id) FROM \"$t\"), 1))");
    } catch (Throwable $e) {}
}
$out[] = 'done';

echo implode("\n", $out) . "\n";
