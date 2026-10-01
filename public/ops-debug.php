<?php
// TEMPORAL - diagnóstico 500 en /lineup. BORRAR tras usar.
if (($_GET['token'] ?? '') !== 'dbg-lineup-20261001') { http_response_code(403); exit('no'); }

require __DIR__.'/../vendor/autoload.php';

$mode = $_GET['mode'] ?? 'trace';
if ($mode === 'health') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "== DB ==\n";
    try {
        $pdo = new PDO(getenv('DB_URL') ?: '', null, null, [PDO::ATTR_TIMEOUT => 5]);
        echo "DB_URL directa: OK\n";
    } catch (Throwable $e) {
        echo "DB_URL directa: ".get_class($e).": ".$e->getMessage()."\n";
    }
    echo "DB_HOST=".getenv('DB_HOST')." DB_DATABASE=".getenv('DB_DATABASE')." DB_USERNAME=".getenv('DB_USERNAME')."\n";
    echo "== DISCO /data ==\n";
    echo "escribible /data: ".(is_writable('/data') ? 'SI' : 'NO')."\n";
    echo "escribible /data/framework/views: ".(is_writable('/data/framework/views') ? 'SI' : 'NO')."\n";
    $df = disk_free_space('/data');
    echo "espacio libre /data: ".($df === false ? '?' : round($df/1024/1024).' MB')."\n";
    echo "== BOOT LARAVEL ==\n";
    try {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $req = Illuminate\Http\Request::create('/up', 'GET');
        $resp = $kernel->handle($req);
        echo "GET /up via kernel: HTTP ".$resp->getStatusCode()."\n";
        if ($resp->getStatusCode() >= 500) {
            echo substr($resp->getContent(), 0, 300)."\n";
        }
    } catch (Throwable $e) {
        echo "EXCEPCION EN BOOT/HANDLE: ".get_class($e)."\n";
        echo "MENSAJE: ".$e->getMessage()."\n";
        echo "EN: ".$e->getFile().":".$e->getLine()."\n";
        echo "TRACE:\n".$e->getTraceAsString()."\n";
    }
    exit;
}
if ($mode === 'full') {
    header('Content-Type: text/plain; charset=utf-8');
    try {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        // Que el manejador de excepciones relance en vez de convertir a 500
        $app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, function ($app) {
            return new class($app) extends Illuminate\Foundation\Exceptions\Handler {
                public function render($request, Throwable $e) { throw $e; }
            };
        });
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $user = App\Models\User::where('email', 'qadual20261001@example.com')->firstOrFail();
        $req = Illuminate\Http\Request::create('/game/2ed63cd6-68df-47c8-b439-bc59db750f1d/lineup', 'GET');
        // Simular sesion autenticada
        $req->setLaravelSession($app['session']->driver());
        $app['auth']->setUser($user);
        $resp = $kernel->handle($req);
        echo "HTTP: ".$resp->getStatusCode()."\n";
        if ($resp->getStatusCode() >= 400) {
            echo substr(strip_tags($resp->getContent()), 0, 500)."\n";
        } else {
            echo "OK, longitud: ".strlen($resp->getContent())."\n";
        }
    } catch (Throwable $e) {
        echo "EXCEPCION REAL: ".get_class($e)."\n";
        echo "MENSAJE: ".$e->getMessage()."\n";
        echo "EN: ".$e->getFile().":".$e->getLine()."\n";
        $prev = $e->getPrevious();
        $i = 1;
        while ($prev) {
            echo "--- CAUSA $i: ".get_class($prev)." ---\n";
            echo "MENSAJE: ".$prev->getMessage()."\n";
            echo "EN: ".$prev->getFile().":".$prev->getLine()."\n";
            $prev = $prev->getPrevious();
            $i++;
            if ($i > 4) break;
        }
        echo "TRACE:\n".substr($e->getTraceAsString(), 0, 4000)."\n";
    }
    exit;
}
if ($mode === 'log') {
    echo "DIR /data/logs: ".implode(', ', array_diff(scandir('/data/logs'), ['.', '..']))."\n";
    echo "LOG_CHANNEL env: ".getenv('LOG_CHANNEL')."\n";
    $log = '/data/logs/laravel-'.date('Y-m-d').'.log';
    if (!is_file($log)) { $log = '/data/logs/laravel.log'; }
    if (!is_file($log)) { echo "SIN LOG\n"; exit; }
    $lines = file($log);
    echo "LOG: $log (".count($lines)." lineas)\n";
    echo "=== ULTIMAS 120 LINEAS ===\n";
    echo implode('', array_slice($lines, -120));
    exit;
}

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$gameId = $_GET['game'] ?? '2ed63cd6-68df-47c8-b439-bc59db750f1d';

try {
    $user = App\Models\User::where('email', 'qadual20261001@example.com')->firstOrFail();
    auth()->setUser($user);
    // En peticiones web reales el middleware ShareErrorsFromSession comparte $errors;
    // lo simulamos para no enmascarar el error REAL que hay mas abajo en la vista.
    $app['view']->share('errors', new Illuminate\Support\ViewErrorBag);
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
