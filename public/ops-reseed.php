<?php
$token = $_GET['token'] ?? '';
if ($token !== 'q7xK9mP2vL4nQ8w') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $exit = \Illuminate\Support\Facades\Artisan::call('app:seed-reference-data');
    echo "EXIT: $exit\n";
    $out = \Illuminate\Support\Facades\Artisan::output();
    // Show first 3000 chars to see where it fails
    echo substr($out, 0, 3000);
} catch (\Throwable $e) {
    echo "EXCEPTION: ".get_class($e).": ".$e->getMessage()."\n";
    echo $e->getTraceAsString();
}
