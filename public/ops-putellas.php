<?php
// TEMPORAL - diagnostico Putellas en partida QA. Borrar tras usar.
$token = $_GET['t'] ?? '';
if (!hash_equals('a8297a53239137937dc1fa44b4f1d937854233439e869dbe4dbc87c9774208b1', hash('sha256', $token))) { http_response_code(403); exit('forbidden'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
header('Content-Type: application/json');
$gameId = '1e27e762-522d-4b10-b33d-cc03e124136e';
$out = [];
try {
    // 1. ¿Está Putellas en game_players de esta partida?
    $gp = DB::table('game_players')->where('game_id', $gameId)
        ->where('name', 'like', '%Putellas%')->first();
    $out['in_game_players'] = $gp ? ['name' => $gp->name, 'overall' => $gp->overall_score, 'team_id' => substr($gp->team_id,0,8)] : null;

    // 2. ¿Está lesionada? (buscar en game_player_match_state o injuries)
    if ($gp) {
        $state = DB::table('game_player_match_state')->where('game_player_id', $gp->id)->first();
        $out['match_state'] = $state ? (array)$state : null;
        // buscar tabla de lesiones
        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename LIKE '%injur%'");
        $out['injury_tables'] = array_column($tables, 'tablename');
    }

    // 3. ¿Cuántas jugadoras tiene España en esta partida?
    $spainTeam = DB::table('teams')->where('name', 'España')->first();
    if ($spainTeam) {
        $count = DB::table('game_players')->where('game_id', $gameId)
            ->where('team_id', $spainTeam->id)->count();
        $out['spain_in_game'] = $count;
        $out['spain_team_id'] = substr($spainTeam->id, 0, 8);
    }

    // 4. ¿Putellas en plantillas?
    $tpl = DB::table('game_player_templates as t')
        ->join('teams as tm', 'tm.id', '=', 't.team_id')
        ->where('t.season', '2026')->where('t.name', 'like', '%Putellas%')
        ->select('t.name', 't.overall_score', 'tm.name as team')->get();
    $out['templates'] = $tpl;

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
