<?php
// TEMPORAL - diagnostico patch. Borrar tras usar.
$token = $_GET['t'] ?? '';
if (!hash_equals('a8297a53239137937dc1fa44b4f1d937854233439e869dbe4dbc87c9774208b1', hash('sha256', $token))) { http_response_code(403); exit('forbidden'); }
ini_set('display_errors', '1');
error_reporting(E_ALL);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
header('Content-Type: application/json');
try {
    $games = DB::table('games as g')
        ->join('teams as t', 't.id', '=', 'g.team_id')
        ->where('t.type', '!=', 'national')
        ->select('g.id', 't.name as team')->get();
    $out = ['games' => count($games)];
    // contar plantillas nuevas (IDs 97xxxxx) que faltan por juego
    $newTemplates = DB::table('game_player_templates')
        ->where('season', '2026')
        ->where('transfermarkt_id', 'like', '97%')
        ->count();
    $out['new_templates_97'] = $newTemplates;
    echo json_encode($out);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'trace' => substr($e->getTraceAsString(), 0, 2000)]);
}
