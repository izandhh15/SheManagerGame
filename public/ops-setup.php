<?php

// Temporary ops endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'shemanager-ops-20261001') {
    http_response_code(403);
    exit('forbidden');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? '';

if ($step === 'migrate') {
    try {
        Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        echo "MIGRATE OK:\n";
        echo Illuminate\Support\Facades\Artisan::output();
    } catch (\Throwable $e) {
        http_response_code(500);
        echo "MIGRATE FAIL: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    }
    exit;
}

echo "unknown step";
