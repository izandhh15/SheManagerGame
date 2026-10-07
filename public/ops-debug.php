<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$ids = ['WQUEFA','WQAFC','WQCAF','WQCONC','WQCONM','WQOFC','WWCQ'];
$found = DB::table('competitions')->whereIn('id', $ids)->pluck('id')->toArray();
echo "found: " . count($found) . "/7\n";
echo "missing: " . implode(',', array_diff($ids, $found)) . "\n";
echo "total competitions: " . DB::table('competitions')->count() . "\n";
