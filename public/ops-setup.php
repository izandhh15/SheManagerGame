<?php
// TEMPORAL - 0.3.64: migraciones pendientes en Wasmer (friendships 000034, career_history 000035). BORRAR tras usar.
$token = $_GET['token'] ?? '';
if (!hash_equals('60b17cd14a5604199a1ddba80489b1fa7a2de358b38ccb5aa0e32d0c546add17', $token)) {
    http_response_code(403);
    exit('no');
}

set_time_limit(0);
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

if ($step === 'check') {
    foreach (['friendships', 'career_history'] as $t) {
        echo "$t: ".(Illuminate\Support\Facades\Schema::hasTable($t) ? 'OK' : 'FALTA')."\n";
    }
    echo 'migrations count: '.Illuminate\Support\Facades\DB::table('migrations')->count()."\n";
    exit;
}

echo "step desconocido\n";
