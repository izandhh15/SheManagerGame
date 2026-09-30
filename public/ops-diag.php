<?php
// TEMPORARY diagnostic endpoint - DELETE AFTER USE
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain');

$token = $_GET['token'] ?? '';
if ($token !== 'diag-temp-20260930') {
    http_response_code(403);
    exit('forbidden');
}

$action = $_GET['action'] ?? 'info';

if ($action === 'info') {
    $teams = DB::table('teams')->where('type', 'national')->count();
    $templates = DB::table('game_player_templates')->where('season', '2026')->count();
    echo "national teams: $teams\n";
    echo "templates 2026: $templates\n";
}

if ($action === 'spain') {
    $team = DB::table('teams')->where('type', 'national')->where('fifa_code', 'ESP')->first();
    if (!$team) { echo "ESP not found\n"; exit; }
    echo "team: {$team->name} ({$team->id})\n";
    $pos = DB::table('game_player_templates')
        ->where('season', '2026')
        ->where('team_id', $team->id)
        ->select('position', DB::raw('count(*) as c'))
        ->groupBy('position')
        ->orderByDesc('c')
        ->get();
    foreach ($pos as $p) {
        echo "{$p->position}: {$p->c}\n";
    }
}

if ($action === 'spain_sample') {
    $team = DB::table('teams')->where('type', 'national')->where('fifa_code', 'ESP')->first();
    $rows = DB::table('game_player_templates')
        ->where('season', '2026')
        ->where('team_id', $team->id)
        ->where('position', 'like', '%Forward%')
        ->select('player_id', 'name', 'position', 'overall_score')
        ->limit(10)
        ->get();
    echo "forwards found: " . $rows->count() . "\n";
    foreach ($rows as $r) {
        echo "{$r->player_id} | {$r->name} | {$r->position} | {$r->overall_score}\n";
    }
}

if ($action === 'club_spain') {
    // Check club templates for Spanish players
    $count = DB::table('game_player_templates')
        ->where('season', '2026')
        ->where('nationality', 'like', '%Spain%')
        ->whereNotIn('team_id', function($q) {
            $q->select('id')->from('teams')->where('type', 'national');
        })
        ->count();
    echo "club templates with Spain nationality: $count\n";
    $pos = DB::table('game_player_templates')
        ->where('season', '2026')
        ->where('nationality', 'like', '%Spain%')
        ->whereNotIn('team_id', function($q) {
            $q->select('id')->from('teams')->where('type', 'national');
        })
        ->select('position', DB::raw('count(*) as c'))
        ->groupBy('position')
        ->orderByDesc('c')
        ->limit(15)
        ->get();
    foreach ($pos as $p) {
        echo "{$p->position}: {$p->c}\n";
    }
}
