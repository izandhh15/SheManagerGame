<?php
// TEMPORAL: correr migraciones en prod. Borrar tras usar.
$token = $_GET['token'] ?? '';
if ($token !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$exit = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo "EXIT: $exit\n";
echo Illuminate\Support\Facades\Artisan::output();
