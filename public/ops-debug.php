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
    echo "pending actions: ".($game->hasPendingActions() ? 'SI' : 'no')."\n";
    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            file_put_contents('/tmp/spain-err.txt', $err['message'].' @ '.$err['file'].':'.$err['line']);
        }
    });
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

if ($step === 'read-err') {
    echo file_exists('/tmp/spain-err.txt') ? file_get_contents('/tmp/spain-err.txt') : "sin error fatal\n";
    exit;
}

if ($step === 'debug-valencia') {
    $gameId = '63095934-6523-4255-941c-521b700de747';
    $game = App\Models\Game::find($gameId);
    if (!$game) { echo "juego no encontrado\n"; exit; }
    echo "juego: {$game->id} | fecha: {$game->current_date}\n";
    echo "transition_step: ".var_export($game->season_transition_step, true)." | setup_completed_at: ".var_export($game->setup_completed_at, true)."\n";
    $team = $game->team;
    echo "equipo: ".($team ? $team->name : 'null')."\n";
    $db = Illuminate\Support\Facades\DB::table('game_player_templates')->where('season', '2026');
    echo "plantillas club ES: ".(clone $db)->whereIn('team_id', function ($q) {
        $q->select('id')->from('teams')->where('type', 'club');
    })->count()."\n";
    echo "plantillas Valencia: ".(clone $db)->whereIn('team_id', function ($q) {
        $q->select('id')->from('teams')->where('name', 'like', '%Valencia%')->where('type', 'club');
    })->count()."\n";
    // duplicados por player_id dentro de club templates
    $dups = Illuminate\Support\Facades\DB::select("SELECT player_id, COUNT(*) c FROM game_player_templates WHERE season='2026' AND team_id IN (SELECT id FROM teams WHERE type='club') GROUP BY player_id HAVING COUNT(*) > 1 LIMIT 5");
    echo "player_ids duplicados en club templates: ".count($dups)."\n";
    foreach ($dups as $d) { echo "  {$d->player_id}: {$d->c}\n"; }
    echo "game_players: ".App\Models\GamePlayer::where('game_id', $gameId)->count()."\n";
    echo "partidos: ".App\Models\GameMatch::where('game_id', $gameId)->count()."\n";
    echo "competition_entries: ".App\Models\CompetitionEntry::where('game_id', $gameId)->count()."\n";
    exit;
}

echo "step desconocido\n";
