<?php
$token = $_GET['token'] ?? '';
if ($token !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Game;
use App\Modules\Season\Jobs\SetupNewGame;

$step = $_GET['step'] ?? 'check';
if ($step === 'runsetup') {
    $g = Game::find('a0c07986-aeeb-41af-9f61-4737e251fb5f');
    try {
        $job = new SetupNewGame($g->id);
        $job->handle();
        echo "SetupNewGame executed OK\n";
    } catch (\Throwable $e) {
        echo "FAILED: ".get_class($e).": ".$e->getMessage()."\n";
        echo substr($e->getTraceAsString(), 0, 2000);
    }
    $g->refresh();
    echo "setup=".($g->setup_completed_at ?? 'NULL')." date={$g->current_date}\n";
    echo "matches=".\App\Models\GameMatch::where('game_id',$g->id)->count()."\n";
} else {
    $fails = \DB::table('failed_jobs')->orderBy('failed_at','desc')->limit(3)->get();
    foreach ($fails as $f) echo "FAILED JOB: ".substr($f->payload, 0, 120)."...\n  ".$f->exception."\n---\n";
    if ($fails->isEmpty()) echo "No failed jobs\n";
}
