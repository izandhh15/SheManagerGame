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
$gameId = '1e27e762-522d-4b10-b33d-cc03e124136e';
$out = [];

$out['game'] = DB::table('games')->where('id', $gameId)->first();
$teamId = $out['game']->team_id ?? null;
$out['team'] = $teamId ? DB::table('teams')->where('id', $teamId)->first() : null;

if ($teamId) {
    $out['putellas_in_pool'] = DB::table('game_player_templates')
        ->where('season', '2026')->where('team_id', $teamId)
        ->where('name', 'ilike', '%putellas%')
        ->select('name', 'overall_score', 'position')->get();
    $out['pool_stats'] = DB::table('game_player_templates')
        ->where('season', '2026')->where('team_id', $teamId)
        ->selectRaw('count(*) as n, max(overall_score) as max_ovr')->first();
    $out['pool_top5_mid'] = DB::table('game_player_templates')
        ->where('season', '2026')->where('team_id', $teamId)
        ->whereIn('position', ['Midfielder', 'Defensive Midfield', 'Central Midfield', 'Attacking Midfield', 'Left Midfield', 'Right Midfield'])
        ->orderByDesc('overall_score')->limit(5)
        ->select('name', 'overall_score', 'position')->get();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
