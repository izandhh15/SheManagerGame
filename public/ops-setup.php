<?php
// TEMPORAL - migracion 2026_10_01_000015 (venue economy + affiliate mode). BORRAR tras usar.
$token = $_GET['token'] ?? '';
if (!hash_equals('ed091ead4f780b2d75f62b2656008e90', $token)) {
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
    echo 'games.pair_mode: '.(Illuminate\Support\Facades\Schema::hasColumn('games', 'pair_mode') ? 'OK' : 'FALTA')."\n";
    echo 'game_matches.venue_fee: '.(Illuminate\Support\Facades\Schema::hasColumn('game_matches', 'venue_fee') ? 'OK' : 'FALTA')."\n";
    echo 'migracion 000015: '.Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%000015%')->count()."\n";
    exit;
}

echo "step desconocido\n";
