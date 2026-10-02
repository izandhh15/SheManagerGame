<?php
$token = $_GET['token'] ?? '';
if ($token !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Game;
use App\Modules\Season\Jobs\SetupNewGame;

$g = Game::find('066f662e-a200-4c14-9f6a-fcded8fa2edc');
echo "Before: setup=".($g->setup_completed_at ?? 'NULL')."\n";
try {
    SetupNewGame::dispatchSync($g->id, $g->team_id, $g->competition_id, $g->season, $g->game_mode);
    echo "dispatchSync OK\n";
} catch (\Throwable $e) {
    echo "FAILED: ".get_class($e).": ".$e->getMessage()."\n";
}
$g->refresh();
echo "After: setup=".($g->setup_completed_at ?? 'NULL')." date={$g->current_date}\n";
echo "matches=".\App\Models\GameMatch::where('game_id',$g->id)->count()."\n";
