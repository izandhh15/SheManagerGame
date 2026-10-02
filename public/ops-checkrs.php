<?php
$token = $_GET['token'] ?? '';
if ($token !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Game;
use App\Modules\Season\Jobs\SetupNewGame;

$g = Game::find('a0c07986-aeeb-41af-9f61-4737e251fb5f');
echo "team={$g->team_id} comp={$g->competition_id} season={$g->season} mode={$g->game_mode}\n";
try {
    SetupNewGame::dispatchSync($g->id, $g->team_id, $g->competition_id, $g->season, $g->game_mode);
    echo "SetupNewGame dispatchSync OK\n";
} catch (\Throwable $e) {
    echo "FAILED: ".get_class($e).": ".$e->getMessage()."\n";
    echo substr($e->getTraceAsString(), 0, 1500);
}
$g->refresh();
echo "setup=".($g->setup_completed_at ?? 'NULL')." date={$g->current_date}\n";
echo "matches=".\App\Models\GameMatch::where('game_id',$g->id)->count()."\n";
