<?php
// TEMPORAL: insertar WQC con valores del seeder. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];

$wqcs = [
    'WQUEFA' => 'Clasificación Mundial 2027 · UEFA',
    'WQAFC'  => 'Clasificación Mundial 2027 · AFC',
    'WQCAF'  => 'Clasificación Mundial 2027 · CAF',
    'WQCONC' => 'Clasificación Mundial 2027 · CONCACAF',
    'WQCONM' => 'Clasificación Mundial 2027 · CONMEBOL',
    'WQOFC'  => 'Clasificación Mundial 2027 · OFC',
    'WWCQ'   => "Women's World Cup Qualifiers",
];

try {
    foreach ($wqcs as $id => $name) {
        DB::table('competitions')->updateOrInsert(
            ['id' => $id],
            [
                'name' => $name,
                'country' => 'IN',
                'flag' => null,
                'tier' => 1,
                'type' => 'league',
                'role' => 'league',
                'scope' => 'continental',
                'handler_type' => 'league',
                'season' => '2026',
            ]
        );
    }
    $out[] = 'WQC inserted OK';
    
    $found = DB::table('competitions')->whereIn('id', array_keys($wqcs))->pluck('id')->toArray();
    $out[] = 'verified: ' . count($found) . '/7';
    
    $ntCount = DB::table('teams')->where('type', 'national')->count();
    $out[] = "national teams: $ntCount";
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . substr($e->getMessage(), 0, 300);
}

echo implode("\n", $out) . "\n";
