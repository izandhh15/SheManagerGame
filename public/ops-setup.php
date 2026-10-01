<?php
// Temporary ops endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'TEMP_OPS_20261001_FC27') {
    http_response_code(404);
    exit('Not found');
}

// Catch engine-level fatals (parse errors, OOM, timeouts) that escape try/catch.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR], true)) {
        header('Content-Type: text/plain');
        echo "\nFATAL [{$e['type']}]: {$e['message']} in {$e['file']}:{$e['line']}\n";
    }
});

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? 'info';
header('Content-Type: text/plain');

try {
    switch ($step) {
        case 'info':
            echo "PHP: " . PHP_VERSION . "\n";
            echo "DB: " . config('database.default') . "\n";
            $pending = Artisan::call('migrate:status');
            echo Artisan::output();
            break;

        case 'migrate':
            echo "Running migrations...\n";
            Artisan::call('migrate', ['--force' => true]);
            echo Artisan::output();
            break;

        case 'debug-up':
            // Run each part of migration 000003 separately with markers,
            // catching Throwables per step so we see exactly what fails.
            $migration = require database_path('migrations/2026_10_01_000003_realistic_player_ratings.php');
            $ref = new ReflectionClass($migration);
            foreach (['movePutellasToLondonCity', 'ensurePutellasSpainTemplate', 'refreshAllRatings'] as $m) {
                echo "=== {$m} ===\n";
                if (ob_get_level()) { ob_flush(); }
                flush();
                try {
                    $method = $ref->getMethod($m);
                    $method->setAccessible(true);
                    $method->invoke($migration);
                    echo "OK {$m}\n";
                } catch (Throwable $e) {
                    echo 'THROWABLE ' . get_class($e) . ': ' . $e->getMessage() . "\n";
                    echo $e->getTraceAsString() . "\n";
                }
                if (ob_get_level()) { ob_flush(); }
                flush();
            }
            echo "DONE debug-up\n";
            break;

        case 'verify-alexia':
            $rows = DB::table('game_player_templates')
                ->join('teams', 'teams.id', '=', 'game_player_templates.team_id')
                ->where('game_player_templates.season', '2026')
                ->where('game_player_templates.transfermarkt_id', '904001')
                ->select('teams.name as team', 'teams.type', 'game_player_templates.overall_score', 'game_player_templates.position')
                ->get();
            foreach ($rows as $r) {
                echo "{$r->team} [{$r->type}] overall={$r->overall_score} pos={$r->position}\n";
            }
            if ($rows->isEmpty()) {
                echo "NO TEMPLATES FOUND\n";
            }
            break;

        default:
            echo "Unknown step\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}
