<?php
// Temporary ops endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'TEMP_OPS_20261001_ACADEMY') {
    http_response_code(404);
    exit('Not found');
}

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR], true)) {
        header('Content-Type: text/plain');
        echo "\nFATAL [{$e['type']}]: {$e['message']} in {$e['file']}:{$e['line']}\n";
    }
});

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
            Artisan::call('migrate:status');
            echo Artisan::output();
            break;

        case 'migrate':
            echo "Running migrations...\n";
            Artisan::call('migrate', ['--force' => true]);
            echo Artisan::output();
            break;

        default:
            echo "Unknown step: {$step}\n";
    }
} catch (Throwable $e) {
    echo 'THROWABLE ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
