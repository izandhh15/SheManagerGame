<?php
// Temporary ops endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'TEMP_OPS_20261001_MIG') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? 'info';
header('Content-Type: text/plain');

try {
    switch ($step) {
        case 'info':
            echo "PHP: " . PHP_VERSION . "\n";
            echo "DB: " . config('database.default') . "\n";
            $pending = Artisan::call('migrate:status');
            echo Artisan::output();
            break;

        case 'migrate':
            echo "Running migrations...\n";
            Artisan::call('migrate', ['--force' => true]);
            echo Artisan::output();
            break;

        default:
            echo "Unknown step\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}
