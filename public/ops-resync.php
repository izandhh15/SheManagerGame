<?php
// TEMPORAL - re-sincroniza plantillas de clubs desde JSON + parchea partidas.
// Borrar tras usar.
$token = $_GET['t'] ?? '';
if (!hash_equals('a8297a53239137937dc1fa44b4f1d937854233439e869dbe4dbc87c9774208b1', hash('sha256', $token))) {
    http_response_code(403);
    exit('forbidden');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Captura errores fatales y muestra el mensaje
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['fatal' => $e['message'], 'file' => basename($e['file']), 'line' => $e['line']]);
    }
});

header('Content-Type: application/json');
$out = [];
$step = $_GET['step'] ?? 'refresh';

if ($step === 'refresh') {
    try {
    // 1. Regenera plantillas de CLUBS desde el JSON actual (ratings FC27 incluidos).
    //    No toca las plantillas de selecciones.
    $before = DB::table('game_player_templates')->where('season', '2026')->count();
    $exit = Artisan::call('app:refresh-player-templates', ['--season' => '2026']);
    $out['artisan_exit'] = $exit;
    $out['artisan_output'] = Artisan::output();
    $out['templates_before'] = $before;
    $out['templates_after'] = DB::table('game_player_templates')->where('season', '2026')->count();
    // Verificacion: los clubs que estaban cortos
    $out['check'] = DB::table('game_player_templates as p')
        ->join('teams as t', 't.id', '=', 'p.team_id')
        ->where('p.season', '2026')
        ->whereIn('t.name', ['Grêmio', 'Cruzeiro', 'Internacional', 'Sevilla FC', 'SD Eibar', 'Real Sociedad', 'FC Luzern'])
        ->select('t.name', DB::raw('count(*) as n'), DB::raw('max(p.overall_score) as max_ovr'))
        ->groupBy('t.name')->orderBy('t.name')->get();
    } catch (\Throwable $e) {
        $out['error'] = get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine();
    }
}

if ($step === 'patch-games') {
try {
    // 2. Parchea TODAS las partidas de club de una vez: inserta solo las
    //    plantillas nuevas (transfermarkt_id 97xxxxx, las reasignadas) que
    //    falten en cada partida. Una sola query, sin bucle por partida.
    $games = DB::table('games as g')
        ->join('teams as t', 't.id', '=', 'g.team_id')
        ->where('t.type', '!=', 'national')
        ->select('g.id')->get();
    $gameIds = $games->pluck('id')->all();
    $out['games_count'] = count($gameIds);

    if (count($gameIds) > 0) {
        // INSERT masivo: por cada juego x cada plantilla nueva que falte
        $addedPlayers = DB::insert(<<<SQL
            INSERT INTO game_players (
                id, game_id, player_id,
                transfermarkt_id, sofascore_id, fc26_id, name, date_of_birth, nationality, height, foot,
                team_id, number, position, secondary_positions,
                market_value, market_value_cents, contract_until, annual_wage, release_clause, durability,
                overall_score,
                potential, potential_low, potential_high, tier
            )
            SELECT
                gen_random_uuid(), g.id, t.player_id,
                t.transfermarkt_id, t.sofascore_id, t.fc26_id, t.name, t.date_of_birth, t.nationality, t.height, t.foot,
                t.team_id,
                COALESCE((SELECT MAX(gp2.number) FROM game_players gp2 WHERE gp2.game_id = g.id AND gp2.team_id = t.team_id), 0)
                  + ROW_NUMBER() OVER (PARTITION BY g.id, t.team_id ORDER BY t.player_id),
                t.position, t.secondary_positions,
                t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.release_clause, t.durability,
                t.overall_score,
                t.potential, t.potential_low, t.potential_high, t.tier
            FROM games g
            CROSS JOIN game_player_templates t
            WHERE t.season = '2026'
              AND t.transfermarkt_id LIKE '97%'
              AND t.team_id NOT IN (SELECT id FROM teams WHERE type = 'national')
              AND g.id IN (SELECT g2.id FROM games g2 JOIN teams t2 ON t2.id = g2.team_id WHERE t2.type != 'national')
              AND NOT EXISTS (
                  SELECT 1 FROM game_players gp
                  WHERE gp.game_id = g.id AND gp.player_id = t.player_id
              )
            ON CONFLICT (game_id, player_id) DO NOTHING
        SQL);
        $out['inserted_rows'] = $addedPlayers;

        // match_state para las nuevas
        $addedState = DB::insert(<<<'SQL'
            INSERT INTO game_player_match_state (game_player_id, game_id, fitness, morale)
            SELECT gp.id, gp.game_id, t.fitness, t.morale
            FROM game_players gp
            JOIN game_player_templates t
              ON t.player_id = gp.player_id
             AND t.team_id = gp.team_id
             AND t.season = '2026'
             AND t.transfermarkt_id LIKE '97%'
            WHERE NOT EXISTS (
                SELECT 1 FROM game_player_match_state s WHERE s.game_player_id = gp.id
            )
            ON CONFLICT (game_player_id) DO NOTHING
        SQL);
        $out['inserted_state_rows'] = $addedState;
    }
    $out['games_patched'] = 'bulk';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'class' => get_class($e)]);
    exit;
}
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
