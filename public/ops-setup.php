<?php
// TEMPORAL - migracion 2026_10_01_000016 (matchday pricing). BORRAR tras usar.
$token = $_GET['token'] ?? '';
if (!hash_equals('f101be247ae213f730c07d653284eb84', $token)) {
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
    foreach (['ticket_price','shirt_price','merch_price','bar_price'] as $c) {
        echo "games.$c: ".(Illuminate\Support\Facades\Schema::hasColumn('games', $c) ? 'OK' : 'FALTA')."\n";
    }
    echo 'migracion 000016: '.Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%000016%')->count()."\n";
    exit;
}

echo "step desconocido\n";
