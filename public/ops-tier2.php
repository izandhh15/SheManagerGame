<?php
// Temporary ops endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'TEMP_OPS_20260930_TIER2') {
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
            break;
            
        case 'seed-tier2':
            echo "Seeding FRA2, ITA2, DEU2, ENG2...\n";
            foreach (['FR', 'IT', 'DE', 'EN'] as $country) {
                echo "Seeding $country...\n";
                Artisan::call('app:seed-reference-data', ['--country' => $country]);
                echo Artisan::output();
            }
            echo "Done.\n";
            break;
            
        default:
            echo "Unknown step: $step\n";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}
