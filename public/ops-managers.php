<?php
// TEMPORAL - borrar tras usar. Rellena teams.manager_name desde los JSON.
$token = $_GET['token'] ?? '';
if ($token !== 'mN8xK2pL5vQ9wR4tY7') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$step = $_GET['step'] ?? '';

if ($step === 'migrate') {
    $exit = Artisan::call('migrate', ['--force' => true]);
    echo "MIGRATE EXIT: $exit\n";
    echo substr(Artisan::output(), -3000);
    echo "has manager_name: ".(Schema::hasColumn('teams', 'manager_name') ? 'YES' : 'NO')."\n";
    exit;
}

if ($step === 'diag') {
    $rows = DB::table('teams')->where('type', 'national')
        ->where(function($q){ $q->whereNull('fifa_code')->orWhere('fifa_code',''); })
        ->get(['id','name','fifa_code','manager_name']);
    echo "national teams with empty fifa_code: ".$rows->count()."\n";
    foreach ($rows as $r) echo " - {$r->name} | manager: ".($r->manager_name ?? '(null)')."\n";
    exit;
}

if ($step === 'names') {
    $base = __DIR__.'/../data/2026';
    $updated = 0; $missing = 0; $files = 0;
    foreach (glob($base.'/*/teams.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if (!isset($d['clubs'])) continue;
        $files++;
        foreach ($d['clubs'] as $c) {
            $mn = $c['managerName'] ?? null;
            if (!$mn) continue;
            $tm = $c['transfermarktId'] ?? null;
            if (!$tm) continue;
            $n = DB::table('teams')->where('transfermarkt_id', $tm)->update(['manager_name' => $mn]);
            if ($n) $updated++; else $missing++;
        }
    }
    // Selecciones
    $nat = json_decode(file_get_contents($base.'/NAT.json'), true)['clubs'] ?? [];
    $natUp = 0; $natMiss = 0;
    foreach ($nat as $c) {
        $mn = $c['managerName'] ?? null;
        if (!$mn) continue;
        $fc = $c['fifa_code'] ?? null;
        if ($fc) {
            $n = DB::table('teams')->where('type', 'national')->where('fifa_code', $fc)->update(['manager_name' => $mn]);
        } else {
            // Youth teams have no fifa_code: match by exact name.
            $n = DB::table('teams')->where('type', 'national')->where('name', $c['name'])->update(['manager_name' => $mn]);
        }
        if ($n) $natUp++; else $natMiss++;
    }
    echo "club files: $files | clubs updated: $updated | clubs not found: $missing\n";
    echo "national updated: $natUp | national not found: $natMiss\n";
    $total = DB::table('teams')->whereNotNull('manager_name')->count();
    echo "teams with manager_name now: $total\n";
    exit;
}

echo "usage: ?token=...&step=migrate|names\n";
