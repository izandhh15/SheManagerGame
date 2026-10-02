<?php
// TEMPORAL - diagnóstico del 500 en /advance y del setup atascado. BORRAR tras usar.
$token = $_GET['token'] ?? '';
if (!hash_equals('72b400a781fc12b82d1c347048f7fde7', $token)) {
    http_response_code(403);
    exit('no');
}

set_time_limit(0);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? '';

if ($step === 'debug-spain') {
    $gameId = '8a46c186-84e0-42c6-9143-9fa3746f82cb';
    $game = App\Models\Game::find($gameId);
    if (!$game) { echo "juego no encontrado\n"; exit; }
    echo "juego: {$game->id} | fecha: {$game->current_date} | advancing_at: ".($game->matchday_advancing_at ?? 'null')."\n";
    echo "pending actions: ".App\Models\GameAction::where('game_id', $gameId)->whereNull('resolved_at')->count()."\n";
    try {
        $coordinator = app(App\Modules\Match\Services\MatchdayAdvanceCoordinator::class);
        $result = $coordinator->runSync($gameId);
        echo "OK: ".($result ? get_class($result) : 'null')."\n";
    } catch (\Throwable $e) {
        echo "EXCEPCION: ".get_class($e)."\n";
        echo "MENSAJE: ".$e->getMessage()."\n";
        echo "ARCHIVO: ".$e->getFile().":".$e->getLine()."\n";
        $trace = explode("\n", $e->getTraceAsString());
        echo "TRACE:\n".implode("\n", array_slice($trace, 0, 25))."\n";
    }
    exit;
}

if ($step === 'debug-valencia') {
    $gameId = '63095934-6523-4255-941c-521b700de747';
    $game = App\Models\Game::find($gameId);
    if (!$game) { echo "juego no encontrado\n"; exit; }
    echo "juego: {$game->id} | fecha: {$game->current_date}\n";
    $cols = [];
    foreach (['season_transitioning_at', 'setup_step', 'setup_progress'] as $c) {
        try { $cols[$c] = $game->getAttribute($c); } catch (\Throwable $e) { $cols[$c] = 'N/A'; }
    }
    echo "setup: ".json_encode($cols)."\n";
    $team = $game->team;
    echo "equipo: ".($team ? $team->name : 'null')."\n";
    $count = Illuminate\Support\Facades\DB::table('game_player_templates')
        ->where('season', '2026')
        ->whereIn('team_id', function ($q) {
            $q->select('id')->from('teams')->where('name', 'like', '%Valencia%')->where('type', 'club');
        })->count();
    echo "plantillas Valencia: $count\n";
    echo "game_players: ".App\Models\GamePlayer::where('game_id', $gameId)->count()."\n";
    echo "partidos: ".App\Models\GameMatch::where('game_id', $gameId)->count()."\n";
    exit;
}

echo "step desconocido\n";
