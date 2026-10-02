<?php
// TEMPORARY ops endpoint — DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;

$step = $_GET['step'] ?? 'find';

if ($step === 'find') {
    $teams = Team::where('name', 'like', '%Real Sociedad%')->get(['id', 'name', 'country', 'transfermarkt_id']);
    foreach ($teams as $t) {
        echo "{$t->id} | {$t->name} | {$t->country} | tm={$t->transfermarkt_id}\n";
    }
    if ($teams->isEmpty()) echo "NONE FOUND\n";
}
