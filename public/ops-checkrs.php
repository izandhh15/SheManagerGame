<?php
$token = $_GET['token'] ?? '';
if ($token !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Game;
$g = Game::find('a0c07986-aeeb-41af-9f61-4737e251fb5f');
echo "Club: setup=".($g->setup_completed_at ?? 'NULL')." date={$g->current_date} season={$g->season}\n";
$g2 = Game::find('cc8bc3bd-da17-4871-ba9e-00d3f31537f8');
echo "NT: setup=".($g2->setup_completed_at ?? 'NULL')." date={$g2->current_date}\n";
echo "Club matches: ".\App\Models\GameMatch::where('game_id',$g->id)->count()."\n";
