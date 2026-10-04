<?php
// TEMPORAL: obtener config DB actual. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = config('database.connections.pgsql');
echo json_encode([
    'host' => $c['host'] ?? null,
    'port' => $c['port'] ?? null,
    'database' => $c['database'] ?? null,
    'username' => $c['username'] ?? null,
    // no mostramos el password completo por seguridad
    'has_password' => !empty($c['password']),
]) . "\n";
