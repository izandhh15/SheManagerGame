<?php
// TEMPORAL: resembrar WQC en nueva BD.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$competitions = [
    ['WQUEFA', 'Clasificación Mundial 2027 · UEFA'],
    ['WQAFC', 'Clasificación Mundial 2027 · AFC'],
    ['WQCAF', 'Clasificación Mundial 2027 · CAF'],
    ['WQCONC', 'Clasificación Mundial 2027 · CONCACAF'],
    ['WQCONM', 'Clasificación Mundial 2027 · CONMEBOL'],
    ['WQOFC', 'Clasificación Mundial 2027 · OFC'],
    ['WWCQ', "Women's World Cup Qualifiers"],
];

$out = [];
foreach ($competitions as [$id, $name]) {
    $exists = DB::table('competitions')->where('id', $id)->exists();
    if ($exists) {
        $out[] = "$id: ya existe";
        continue;
    }
    DB::table('competitions')->insert([
        'id' => $id,
        'name' => $name,
        'type' => 'league',
        'role' => 'league',
        'scope' => 'continental',
        'country' => 'IN',
        'tier' => 1,
        'handler_type' => 'league',
        'season' => 2026,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $out[] = "$id: creado";
}

echo implode("\n", $out) . "\n";
