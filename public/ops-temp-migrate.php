<?php
// TEMPORAL - ejecuta migración de eventos de selección. Borrar tras usar.
if (($_GET['t'] ?? '') !== 'temp-migrate-01102026') {
    http_response_code(403);
    exit('Forbidden');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;

header('Content-Type: application/json');
$out = [];
try {
    $exit = Artisan::call('migrate', ['--force' => true]);
    $out['exit'] = $exit;
    $out['output'] = Artisan::output();
} catch (Throwable $e) {
    $out['error'] = get_class($e).': '.$e->getMessage();
}
echo json_encode($out);
