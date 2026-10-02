<?php
// TEMPORAL - regenerar plantillas ES (clubes + selecciones) tras fix Carballada. BORRAR tras usar.
$token = $_GET['token'] ?? '';
if (!hash_equals('75cace1b9b915f8bb54e30504150289c', $token)) {
    http_response_code(403);
    exit('no');
}

set_time_limit(0);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? '';

if ($step === 'refresh-es') {
    $code = Illuminate\Support\Facades\Artisan::call('app:refresh-player-templates', ['--country' => 'ES', '--season' => '2026']);
    echo "club exit=$code\n";
    echo Illuminate\Support\Facades\Artisan::output();
    $code2 = Illuminate\Support\Facades\Artisan::call('app:seed-national-teams');
    echo "nt exit=$code2\n";
    echo Illuminate\Support\Facades\Artisan::output();
    exit;
}

echo "step desconocido\n";
