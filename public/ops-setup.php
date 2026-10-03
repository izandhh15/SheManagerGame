<?php
// TEMPORAL: reparar ledger de migraciones + aplicar pendientes. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

$out = [];
try {
    // 1. Estado del ledger
    $hasLedger = Schema::hasTable('migrations');
    $ran = $hasLedger ? DB::table('migrations')->pluck('migration')->all() : [];
    $out[] = 'ledger_exists=' . ($hasLedger ? 1 : 0) . ' ran_count=' . count($ran);

    // 2. Marcadores de esquema (migraciones 34-37 ya aplicadas en la BD?)
    $markers = [
        'friendships table (000034)' => Schema::hasTable('friendships'),
        'career_history table (000035)' => Schema::hasTable('career_history'),
        'social_posts.poster (000036)' => Schema::hasColumn('social_posts', 'poster'),
        'friendships.is_federated (000037)' => Schema::hasColumn('friendships', 'is_federated'),
        'academy_players.growth_progress (000038)' => Schema::hasColumn('academy_players', 'growth_progress'),
    ];
    foreach ($markers as $k => $v) {
        $out[] = 'marker ' . $k . '=' . ($v ? 1 : 0);
    }

    // 3. Si el ledger está vacío pero el esquema está completo (34-37 presentes),
    //    la BD se construyó fuera del migrador: dar de alta 000001..000037 como ejecutadas.
    $schemaComplete = $markers['friendships table (000034)']
        && $markers['career_history table (000035)']
        && $markers['social_posts.poster (000036)']
        && $markers['friendships.is_federated (000037)'];
    if ($schemaComplete && count($ran) === 0) {
        if (!$hasLedger) {
            Schema::create('migrations', function ($table) {
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });
            $out[] = 'migrations table created';
        }
        $files = glob(database_path('migrations/*.php'));
        $names = array_map(fn($f) => basename($f, '.php'), $files);
        sort($names);
        $backfill = array_values(array_filter($names, fn($n) => !str_contains($n, '000038')));
        foreach ($backfill as $name) {
            DB::table('migrations')->insert(['migration' => $name, 'batch' => 1]);
        }
        $out[] = 'backfilled=' . count($backfill) . ' migrations marked as run (batch 1)';
    } elseif (!$schemaComplete) {
        $out[] = 'ABORT: schema markers incomplete, no backfill attempted';
        echo implode("\n", $out) . "\n";
        exit;
    }

    // 4. Aplicar pendientes (debería ser solo 000038)
    $code = Artisan::call('migrate', ['--force' => true]);
    $out[] = 'migrate EXIT: ' . $code;
    $out[] = Artisan::output();
    $out[] = 'final growth_progress=' . (Schema::hasColumn('academy_players', 'growth_progress') ? 1 : 0);
} catch (Throwable $e) {
    $out[] = 'ERROR: ' . $e->getMessage();
}
echo implode("\n", $out) . "\n";
