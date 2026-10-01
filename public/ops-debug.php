<?php
// TEMPORAL - debug del seeder. Borrar tras usar.
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

// Que hay en BD para Cruzeiro/Gremio ahora
$out['db_cruzeiro'] = DB::table('game_player_templates as p')
    ->join('teams as t', 't.id', '=', 'p.team_id')
    ->where('p.season', '2026')->where('t.name', 'Cruzeiro')
    ->select('p.name', 'p.player_id', 'p.transfermarkt_id')->get();
$out['db_cruzeiro_count'] = count($out['db_cruzeiro']);

// JSON de BRA1/Cruzeiro
$json = json_decode(file_get_contents(base_path('data/2026/BRA1/teams.json')), true);
foreach ($json['clubs'] as $c) {
    if ($c['name'] === 'Cruzeiro') {
        $out['json_cruzeiro_ids'] = array_map(fn($p) => $p['id'] ?? null, $c['players']);
        $out['json_cruzeiro_names'] = array_map(fn($p) => $p['name'], $c['players']);
        break;
    }
}

// El team_id de Cruzeiro en BD y su transfermarkt_id
$out['team_cruzeiro'] = DB::table('teams')->where('name', 'Cruzeiro')->select('id', 'name', 'transfermarkt_id')->first();

// Que competiciones procesa BR segun CountryConfig
$cc = app(App\Modules\Competition\Services\CountryConfig::class);
$out['br_order'] = $cc->playerInitializationOrder('BR');
$out['es_order'] = $cc->playerInitializationOrder('ES');

// Cuantas plantillas hay por pais de equipo ahora
$out['templates_by_country'] = DB::table('game_player_templates as p')
    ->join('teams as t', 't.id', '=', 'p.team_id')
    ->where('p.season', '2026')->where('t.type', '!=', 'national')
    ->select('t.country', DB::raw('count(*) as n'), DB::raw('count(distinct p.team_id) as teams'))
    ->groupBy('t.country')->orderBy('n', 'desc')->get();

// Quien gana cada UUID duplicado en la BD actual
require __DIR__.'/uuid_list.php';
$winners = [];
foreach (array_chunk($uuidList, 40) as $chunk) {
    $rows = DB::table('game_player_templates as p')
        ->join('teams as t', 't.id', '=', 'p.team_id')
        ->where('p.season', '2026')
        ->whereIn('p.player_id', $chunk)
        ->select('p.player_id', 'p.name', 't.name as team')
        ->get();
    foreach ($rows as $r) { $winners[$r->player_id] = ['name' => $r->name, 'team' => $r->team]; }
}
$out['winners'] = $winners;
$out['winners_count'] = count($winners);

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
