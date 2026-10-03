<?php
// TEMPORAL: ejecutar migraciones pendientes. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $code = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    echo "EXIT: $code\n";
    echo Illuminate\Support\Facades\Artisan::output();
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
}
