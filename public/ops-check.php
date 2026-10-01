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

// 1. ¿Corrió la migración 000003?
$out['migration_000003'] = DB::table('migrations')
    ->where('migration', 'like', '%000003%')->pluck('migration')->all();

// 2. Equipos nacionales España
$out['spain_teams'] = DB::table('teams')
    ->where('type', 'national')
    ->where(function ($q) { $q->where('fifa_code', 'ESP')->orWhere('name', 'ilike', '%spain%'); })
    ->select('id', 'name', 'fifa_code', 'is_placeholder')->get();

// 3. Plantillas de Putellas en 2026
$out['putellas'] = DB::table('game_player_templates as p')
    ->join('teams as t', 't.id', '=', 'p.team_id')
    ->where('p.season', '2026')
    ->where('p.name', 'ilike', '%putellas%')
    ->select('t.name as team', 't.type as team_type', 'p.name', 'p.overall_score', 'p.position', 'p.transfermarkt_id', 'p.player_id')
    ->get();

// 4. ¿Cuántas plantillas 2026 tiene España?
$spainId = DB::table('teams')->where('type', 'national')->where('fifa_code', 'ESP')->value('id');
$out['spain_templates_count'] = $spainId
    ? DB::table('game_player_templates')->where('season', '2026')->where('team_id', $spainId)->count()
    : null;

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
