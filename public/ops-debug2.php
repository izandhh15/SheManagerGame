<?php
// TEMPORAL - reproducir 500 al confirmar convocatoria. BORRAR tras usar.
$token = $_GET['token'] ?? '';
if (!hash_equals('3970c5fefdb651140c1d21cab7d0d8f0', $token)) {
    http_response_code(403);
    exit('no');
}

set_time_limit(0);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? '';

if ($step === 'repro-convo') {
    $gameId = '8a46c186-84e0-42c6-9143-9fa3746f82cb';
    $game = App\Models\Game::find($gameId);
    if (!$game) { echo "juego no encontrado\n"; exit; }
    echo "fecha juego: {$game->current_date}\n";

    // Coger 23 player_ids válidos de las plantillas de España
    $playerIds = Illuminate\Support\Facades\DB::table('game_player_templates')
        ->where('season', '2026')
        ->where('team_id', $game->team_id)
        ->orderByDesc('overall_score')
        ->limit(23)
        ->pluck('player_id')
        ->map(fn ($id) => (string) $id)
        ->all();
    echo "player_ids: ".count($playerIds)."\n";

    $window = App\Modules\Season\Services\NationalSquadService::relevantWindow($game);
    echo "window: ".json_encode($window)."\n";

    try {
        Illuminate\Support\Facades\DB::transaction(function () use ($game, $playerIds, $window) {
            App\Modules\Season\Services\NationalSquadService::syncSquadPlayers($game, $playerIds);
            $game->national_squad_player_ids = $playerIds;
            if ($window) {
                $game->national_squad_window = $window['start'];
            }
            $game->save();
        });
        echo "OK - convocatoria confirmada\n";
    } catch (\Throwable $e) {
        echo "EXCEPCION: ".get_class($e)."\n";
        echo "MENSAJE: ".$e->getMessage()."\n";
        echo "ARCHIVO: ".$e->getFile().":".$e->getLine()."\n";
        $trace = explode("\n", $e->getTraceAsString());
        echo "TRACE:\n".implode("\n", array_slice($trace, 0, 20))."\n";
    }
    exit;
}

echo "step desconocido\n";
