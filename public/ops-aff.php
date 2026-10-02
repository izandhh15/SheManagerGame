<?php

// TEMPORAL — deploy 02-10-2026 (affiliate redesign): run pending migrations,
// then DELETE this file and redeploy clean. Do not leave in prod.
$token = $_GET['token'] ?? '';
if ($token !== 'aF7qZ3mK9vX2bN5pL8') {
    http_response_code(403);
    exit('no');
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? '';

if ($step === 'migrate') {
    $code = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    header('Content-Type: text/plain; charset=utf-8');
    echo "exit=$code\n";
    echo Illuminate\Support\Facades\Artisan::output();
    exit;
}

if ($step === 'check') {
    header('Content-Type: text/plain; charset=utf-8');
    $has = Illuminate\Support\Facades\Schema::hasColumn('teams', 'manager_name');
    echo 'manager_name column: '.($has ? 'YES' : 'NO')."\n";
    $pending = Illuminate\Support\Facades\Artisan::call('migrate:status');
    echo Illuminate\Support\Facades\Artisan::output();
    exit;
}

echo "usage: ?token=...&step=migrate|check\n";
