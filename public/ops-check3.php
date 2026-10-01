<?php
// TEMPORAL - solo lectura - borrar tras usar.
$token = $_GET['t'] ?? '';
if (!hash_equals('a8297a53239137937dc1fa44b4f1d937854233439e869dbe4dbc87c9774208b1', hash('sha256', $token))) {
    http_response_code(403);
    exit('forbidden');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

header('Content-Type: application/json');
$out = [];

$out['games'] = DB::table('games as g')
    ->join('teams as t', 't.id', '=', 'g.team_id')
    ->select('g.id', 't.name as team', 't.type', 'g.season', 'g.created_at')
    ->orderBy('g.created_at', 'desc')->limit(10)->get();

// plantillas por equipo en la BD (temporada 2026), las 25 mas cortas no-nacionales
$out['shortest_club_templates'] = DB::table('game_player_templates as p')
    ->join('teams as t', 't.id', '=', 'p.team_id')
    ->where('p.season', '2026')->where('t.type', '!=', 'national')
    ->select('t.name', DB::raw('count(*) as n'), DB::raw('max(p.overall_score) as max_ovr'))
    ->groupBy('t.name')->orderBy('n')->limit(25)->get();

// jugadores por equipo en la partida de club mas reciente (si existe)
$clubGame = DB::table('games as g')->join('teams as t', 't.id', '=', 'g.team_id')
    ->where('t.type', '!=', 'national')->orderBy('g.created_at', 'desc')
    ->select('g.id')->first();
$out['club_game_id'] = $clubGame->id ?? null;
if ($clubGame) {
    $out['club_game_squads'] = DB::table('game_players as gp')
        ->join('teams as t', 't.id', '=', 'gp.team_id')
        ->where('gp.game_id', $clubGame->id)
        ->select('t.name', DB::raw('count(*) as n'))
        ->groupBy('t.name')->orderBy('n')->limit(25)->get();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
