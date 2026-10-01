<?php
// TEMPORAL - migracion 2026_10_01_000014 (tracking de visitas). BORRAR tras usar.
$token = $_GET['token'] ?? '';
if (!hash_equals('3492437086392a97d7537254bb7f90da', $token)) {
    http_response_code(403);
    exit('no');
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? 'migrate';

if ($step === 'migrate') {
    $code = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    echo "exit=$code\n";
    echo Illuminate\Support\Facades\Artisan::output();
    exit;
}

if ($step === 'tables') {
    foreach (['visitor_heartbeats', 'traffic_daily', 'traffic_hourly', 'traffic_visitor_days'] as $t) {
        echo $t.': '.(Illuminate\Support\Facades\Schema::hasTable($t) ? 'OK' : 'FALTA')."\n";
    }
    echo 'migrations con 000014: '.Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%000014%')->count()."\n";
    exit;
}

if ($step === 'check') {
    // Inserta un latido sintetico para verificar el pipeline de punta a punta.
    $now = now();
    Illuminate\Support\Facades\DB::table('visitor_heartbeats')->upsert([[
        'visitor_key' => 'check-sintetico-ops',
        'user_id' => null,
        'path' => '/',
        'device' => 'desktop',
        'first_seen' => $now,
        'last_seen' => $now,
    ]], ['visitor_key'], ['path', 'device', 'last_seen']);

    // Endpoint JSON (no necesita layout). $app->call hace la inyeccion de LiveTrafficService.
    $resp = $app->call(App\Http\Views\AdminLiveTrafficData::class.'@__invoke');
    $data = json_decode($resp->getContent(), true);
    echo "JSON online_total: ".$data['online_total']."\n";
    echo "JSON today_visits: ".$data['today_visits']."\n";
    echo "JSON keys: ".implode(',', array_keys($data))."\n";
    $found = false;
    foreach ($data['online_visitors'] as $v) {
        if (($v['path'] ?? '') === '/') { $found = true; break; }
    }
    echo "visitante sintetico en lista: ".($found ? 'SI' : 'NO')."\n";

    // Vista Blade con usuario admin ficticio (el layout exige auth()->user()->is_admin)
    $fake = new App\Models\User();
    $fake->is_admin = true;
    $fake->name = 'Check';
    $fake->email = 'check@local';
    auth()->setUser($fake);
    $viewResp = $app->call(App\Http\Views\AdminLiveTraffic::class.'@__invoke');
    $html = $viewResp->render();
    echo "VIEW OK, longitud: ".strlen($html)."\n";
    echo "VIEW contiene marcador live-data: ".(str_contains($html, 'live-data') ? 'SI' : 'NO')."\n";

    Illuminate\Support\Facades\DB::table('visitor_heartbeats')->where('visitor_key', 'check-sintetico-ops')->delete();
    echo "limpieza OK\n";
    exit;
}

echo "step desconocido\n";
