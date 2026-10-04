<?php
// TEMPORAL: ver constraints de competitions. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];

try {
    $c = DB::select("SELECT conname, pg_get_constraintdef(oid) FROM pg_constraint WHERE conrelid='competitions'::regclass AND contype='c'");
    foreach ($c as $row) {
        $out[] = $row->conname . ': ' . substr($row->pg_get_constraintdef, 0, 300);
    }
    
    // Ver un ejemplo de competicion internacional existente
    $ex = DB::table('competitions')->where('scope', 'international')->first();
    if ($ex) {
        $out[] = 'ejemplo intl: ' . json_encode((array)$ex);
    }
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . substr($e->getMessage(), 0, 200);
}

echo implode("\n", $out) . "\n";
