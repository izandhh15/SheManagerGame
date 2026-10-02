<?php
// TEMPORARY — DELETE AFTER USE
if (($_GET['token'] ?? '') !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, function ($app) {
    return new class($app) extends Illuminate\Foundation\Exceptions\Handler {
        public function render($request, Throwable $e) { throw $e; }
    };
});
try {
    $action = $app->make(App\Http\Views\ShowClubSocial::class);
    $resp = $action('88cb57a3-8e06-4823-a335-327eb39ac1a9');
    echo "OK: ".get_class($resp)."\n";
    $content = $resp->render();
    echo "Rendered ".strlen($content)." bytes\n";
} catch (Throwable $e) {
    echo "EX: ".get_class($e).": ".$e->getMessage()."\n";
    echo "AT: ".$e->getFile().":".$e->getLine()."\n";
}
