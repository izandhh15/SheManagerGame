<?php
// TEMPORAL - regenerar plantillas de selecciones en prod (fix duplicado Mapi León). BORRAR tras usar.
$token = $_GET['token'] ?? '';
if (!hash_equals('b8ca7f67442b68f0701d4ad2fc139db1', $token)) {
    http_response_code(403);
    exit('no');
}

set_time_limit(0);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? '';

if ($step === 'reseed-nt') {
    $code = Illuminate\Support\Facades\Artisan::call('app:seed-national-teams');
    echo "exit=$code\n";
    echo Illuminate\Support\Facades\Artisan::output();
    exit;
}

if ($step === 'check-mapi') {
    $rows = Illuminate\Support\Facades\DB::table('game_player_templates')
        ->where('season', '2026')
        ->where(function ($q) {
            $q->where('name', 'like', '%León%')->orWhere('name', 'like', '%Leon%');
        })
        ->whereIn('team_id', function ($q) {
            $q->select('id')->from('teams')->where('type', 'national')->where('name', 'Spain');
        })
        ->select('player_id', 'name')
        ->get();
    // Sin join a teams: team_id es uuid; mostramos directamente
    $all = Illuminate\Support\Facades\DB::table('game_player_templates as t')
        ->join('teams', 'teams.id', '=', 't.team_id')
        ->where('t.season', '2026')
        ->where('teams.type', 'national')
        ->where('teams.name', 'Spain')
        ->where(function ($q) {
            $q->where('t.name', 'like', '%León%')->orWhere('t.name', 'like', '%Leon%');
        })
        ->select('t.player_id', 't.name')
        ->get();
    foreach ($all as $r) {
        echo $r->player_id.' | '.$r->name."\n";
    }
    echo 'total='.count($all)."\n";
    exit;
}

echo "step desconocido\n";
