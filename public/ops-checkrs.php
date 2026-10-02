<?php
$token = $_GET['token'] ?? '';
if ($token !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Game;
use App\Modules\Season\Services\GameDeletionService;
use App\Modules\Season\Services\GameCreationService;

$step = $_GET['step'] ?? 'check';
if ($step === 'recreate') {
    $oldId = 'a0c07986-aeeb-41af-9f61-4737e251fb5f';
    $old = Game::find($oldId);
    if ($old) {
        app(GameDeletionService::class)->delete($old);
        echo "Deleted old club game\n";
    }
    $clubId = '72c244b6-ed4d-46ef-b664-ce804fbfdbff';
    $userId = '31';
    $gcs = app(GameCreationService::class);
    $g = $gcs->create($userId, $clubId, Game::MODE_CAREER);
    $g->refresh();
    echo "New club game: {$g->id}\n";
    echo "setup=".($g->setup_completed_at ?? 'NULL')." date={$g->current_date}\n";
    echo "matches=".\App\Models\GameMatch::where('game_id',$g->id)->count()."\n";
    // Relink NT game to new club game
    $nt = Game::find('cc8bc3bd-da17-4871-ba9e-00d3f31537f8');
    $nt->update(['linked_game_id' => $g->id]);
    echo "NT relinked to {$g->id}\n";
    echo "NEW_CLUB_GAME_ID={$g->id}\n";
}
