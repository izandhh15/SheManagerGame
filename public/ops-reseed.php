<?php
$token = $_GET['token'] ?? '';
if ($token !== 'q7xK9mP2vL4nQ8w') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$exit = \Illuminate\Support\Facades\Artisan::call('app:seed-reference-data');
echo "EXIT: $exit\n";
echo substr(\Illuminate\Support\Facades\Artisan::output(), -2000);
