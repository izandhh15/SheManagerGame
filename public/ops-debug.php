<?php
// TEMPORAL: insertar competiciones WQC + verificar NTs. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];

$wqcs = [
    'WQUEFA' => 'Clasificación Mundial UEFA',
    'WQAFC'  => 'Clasificación Mundial AFC',
    'WQCAF'  => 'Clasificación Mundial CAF',
    'WQCONC' => 'Clasificación Mundial CONCACAF',
    'WQCONM' => 'Clasificación Mundial CONMEBOL',
    'WQOFC'  => 'Clasificación Mundial OFC',
    'WWCQ'   => 'Clasificación Mundial',
];

try {
    foreach ($wqcs as $id => $name) {
        DB::table('competitions')->updateOrInsert(
            ['id' => $id],
            [
                'name' => $name,
                'country' => 'XX',
                'tier' => 0,
                'type' => 'tournament',
                'season' => '2026',
                'handler_type' => 'world_cup_qualifying',
                'role' => 'qualifier',
                'scope' => 'international',
                'flag' => null,
            ]
        );
    }
    $out[] = 'WQC competitions inserted: ' . count($wqcs);
    
    $found = DB::table('competitions')->whereIn('id', array_keys($wqcs))->pluck('id')->toArray();
    $out[] = 'verified: ' . json_encode($found);
    
    // Verificar si hay equipos nacionales
    $ntCount = DB::table('teams')->where('type', 'national')->count();
    $out[] = "national teams in DB: $ntCount";
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 300);
}

echo implode("\n", $out) . "\n";
