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
    $hasLedger = Schema::hasTable('migrations');
    $ran = $hasLedger ? DB::table('migrations')->pluck('migration')->all() : [];
    $out[] = 'ledger_exists=' . ($hasLedger ? 1 : 0) . ' ran_count=' . count($ran);

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

    // La BD nueva trae esquema hasta 000035 pero ledger vacío (diagnóstico 0.3.89).
    // Dar de alta 000001..000035 como ejecutadas; migrate aplicará 000036..000038 de verdad.
    $upTo35 = $markers['friendships table (000034)'] && $markers['career_history table (000035)'];
    $need3638 = !$markers['social_posts.poster (000036)']
        || !$markers['friendships.is_federated (000037)']
        || !$markers['academy_players.growth_progress (000038)'];
    if ($upTo35 && $need3638 && count($ran) === 0) {
        $files = glob(database_path('migrations/*.php'));
        $names = array_map(fn($f) => basename($f, '.php'), $files);
        sort($names);
        $backfill = array_values(array_filter($names, fn($n) =>
            !str_contains($n, '000036') && !str_contains($n, '000037') && !str_contains($n, '000038')));
        foreach ($backfill as $name) {
            DB::table('migrations')->insert(['migration' => $name, 'batch' => 1]);
        }
        $out[] = 'backfilled=' . count($backfill) . ' (000001..000035) batch=1';
    } elseif (!$upTo35) {
        $out[] = 'ABORT: schema markers incomplete, no backfill attempted';
        echo implode("\n", $out) . "\n";
        exit;
    }

    $code = Artisan::call('migrate', ['--force' => true]);
    $out[] = 'migrate EXIT: ' . $code;
    $out[] = Artisan::output();
    $out[] = 'final poster=' . (Schema::hasColumn('social_posts', 'poster') ? 1 : 0)
        . ' is_federated=' . (Schema::hasColumn('friendships', 'is_federated') ? 1 : 0)
        . ' growth_progress=' . (Schema::hasColumn('academy_players', 'growth_progress') ? 1 : 0);
} catch (Throwable $e) {
    $out[] = 'ERROR: ' . $e->getMessage();
}
echo implode("\n", $out) . "\n";
