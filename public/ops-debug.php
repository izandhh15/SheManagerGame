<?php
// TEMPORAL: ejecutar SeedNationalTeams en prod. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

$out = [];

try {
    // Solo registra las competiciones WQC y equipos nacionales, sin --fresh
    // para no borrar nada existente.
    $exit = Artisan::call('app:seed-national-teams');
    $out[] = "exit: $exit";
    $out[] = "output: " . substr(Artisan::output(), 0, 2000);
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 500);
}

echo implode("\n", $out) . "\n";
