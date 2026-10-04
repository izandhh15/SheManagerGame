<?php
// TEMPORAL: ver schema de competitions y un ejemplo. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];

try {
    $cols = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name='competitions' ORDER BY ordinal_position");
    $out[] = 'cols: ' . json_encode(array_map(fn($c) => $c->column_name, $cols));
    
    $ex = DB::table('competitions')->where('id', 'UWCL')->first();
    if ($ex) {
        $out[] = 'UWCL: ' . json_encode((array)$ex);
    }
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . substr($e->getMessage(), 0, 200);
}

echo implode("\n", $out) . "\n";
