<?php
// Temporary ops endpoint — DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'q7xK9mP2vL4nQ8w') { http_response_code(403); exit('no'); }

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? '';
if ($step === 'seed') {
    $exit = \Illuminate\Support\Facades\Artisan::call('app:seed-reference-data');
    echo "SEED EXIT: $exit\n";
    echo \Illuminate\Support\Facades\Artisan::output();
} elseif ($step === 'check') {
    $uclq = \App\Models\Competition::where('id','UCLQ')->first();
    $uelq = \App\Models\Competition::where('id','UELQ')->first();
    echo "UCLQ: ".($uclq ? "exists ({$uclq->name})" : "MISSING")."\n";
    echo "UELQ: ".($uelq ? "exists ({$uelq->name})" : "MISSING")."\n";
    echo "Teams total: ".\App\Models\Team::count()."\n";
    $torreense = \App\Models\Team::where('name','like','%Torreense%')->first();
    echo "Torreense: ".($torreense ? "exists" : "MISSING")."\n";
} else {
    echo "Use ?step=check or ?step=seed";
}
