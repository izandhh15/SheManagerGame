<?php
// TEMPORAL - solo winners. Borrar tras usar.
$token = $_GET['t'] ?? '';
if (!hash_equals('a8297a53239137937dc1fa44b4f1d937854233439e869dbe4dbc87c9774208b1', hash('sha256', $token))) { http_response_code(403); exit('forbidden'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
require __DIR__.'/uuid_list.php';
header('Content-Type: application/json');
$winners = [];
foreach (array_chunk($uuidList, 40) as $chunk) {
    foreach (DB::table('game_player_templates as p')
        ->join('teams as t', 't.id', '=', 'p.team_id')
        ->where('p.season', '2026')->whereIn('p.player_id', $chunk)
        ->select('p.player_id', 'p.name', 't.name as team')->get() as $r) {
        $winners[$r->player_id] = $r->name . ' @ ' . $r->team;
    }
}
echo json_encode($winners, JSON_UNESCAPED_UNICODE);
