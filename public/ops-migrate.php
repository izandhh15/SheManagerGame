<?php
// TEMPORAL - ejecuta migraciones pendientes. Borrar tras usar.
$token = $_GET['t'] ?? '';
if ($token !== 'putellas-check-01102026-izn') {
    http_response_code(403);
    exit('forbidden');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;

header('Content-Type: application/json');
try {
    $exit = Artisan::call('migrate', ['--force' => true]);
    echo json_encode(['status' => $exit, 'output' => Artisan::output()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
