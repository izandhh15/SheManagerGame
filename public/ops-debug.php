<?php
// TEMPORAL: obtener password DB. BORRAR INMEDIATAMENTE.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
echo config('database.connections.pgsql.password');
