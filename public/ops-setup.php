<?php
// Temporary ops endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'TEMP_OPS_20261001_FC27') {
    http_response_code(404);
    exit('Not found');
}

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
