<?php
// TEMPORAL - diagnóstico 500 en /lineup. BORRAR tras usar.
if (($_GET['token'] ?? '') !== 'dbg-lineup-20261001') { http_response_code(403); exit('no'); }

$mode = $_GET['mode'] ?? 'trace';
if ($mode === 'log') {
    $log = '/data/logs/laravel.log';
    if (!is_file($log)) { echo "SIN LOG en $log\n"; exit; }
    $lines = file($log);
    echo "LOG: $log (".count($lines)." lineas)\n";
    echo "=== ULTIMAS 120 LINEAS ===\n";
    echo implode('', array_slice($lines, -120));
    exit;
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$gameId = $_GET['game'] ?? '2ed63cd6-68df-47c8-b439-bc59db750f1d';

try {
    $user = App\Models\User::where('email', 'qadual20261001@example.com')->firstOrFail();
    auth()->setUser($user);
    $view = $app->make(App\Http\Views\ShowLineup::class);
    $response = $view($gameId);
    echo "OK - sin excepcion. Respuesta: ".get_class($response)."\n";
    $html = $response->render();
    echo "RENDER OK, longitud: ".strlen($html)."\n";
} catch (Throwable $e) {
    echo "EXCEPCION: ".get_class($e)."\n";
    echo "MENSAJE: ".$e->getMessage()."\n";
    echo "EN: ".$e->getFile().":".$e->getLine()."\n";
    echo "TRACE:\n".$e->getTraceAsString()."\n";
}
