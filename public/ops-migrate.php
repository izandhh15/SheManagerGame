<?php
// TEMPORARY ops endpoint — DELETE AFTER USE
if (($_GET['token'] ?? '') !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $exit = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    echo "EXIT: $exit\n";
    echo Illuminate\Support\Facades\Artisan::output();
} catch (Throwable $e) {
    echo "FAILED: ".get_class($e).": ".$e->getMessage()."\n";
}
