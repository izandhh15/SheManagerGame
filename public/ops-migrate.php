<?php
// Temporary: run pending migrations. Delete after use.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

$token = $_GET['t'] ?? '';
if ($token !== 'putellas-check-01102026-izn') {
    http_response_code(403);
    exit('Forbidden');
}

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->call('migrate', ['--force' => true]);

header('Content-Type: application/json');
echo json_encode([
    'status' => $status,
    'output' => $kernel->output(),
]);
