<?php
// TEMPORARY ops endpoint — DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Game;
use Illuminate\Support\Facades\Artisan;

$gameId = $_GET['game'] ?? '066f662e-a200-4c14-9f6a-fcded8fa2edc';
$target = (int)($_GET['to'] ?? 5);

try {
    $exit = Artisan::call('game:simulate', ['gameId' => $gameId, 'matchday' => $target]);
    echo "EXIT: $exit\n";
    echo Artisan::output();
    $g = Game::find($gameId);
    $g->refresh();
    echo "Current date: {$g->current_date}\n";
} catch (\Throwable $e) {
    echo "FAILED: ".get_class($e).": ".$e->getMessage()."\n";
    echo substr($e->getTraceAsString(), 0, 1500);
}
