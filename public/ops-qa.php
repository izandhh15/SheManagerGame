<?php

// Temporary QA endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'shemanager-qa-20261001') {
    http_response_code(403);
    exit('forbidden');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? '';

if ($step === 'simulate') {
    $gameId = $_GET['gameId'] ?? '';
    $matchday = (int) ($_GET['matchday'] ?? 0);
    $maxBatches = min(8, max(1, (int) ($_GET['batches'] ?? 3)));
    if (!$gameId || $matchday <= 0) {
        http_response_code(400);
        exit('missing gameId/matchday');
    }
    // Cap the number of advance batches per HTTP call to stay under edge timeouts.
    // We run game:simulate toward the target but stop early via a custom loop.
    try {
        set_time_limit(240);
        $game = App\Models\Game::find($gameId);
        if (!$game) {
            http_response_code(404);
            exit('game not found');
        }
        Illuminate\Support\Facades\DB::disableQueryLog();
        $fastModeWasEntered = $game->isFastMode();
        if (!$fastModeWasEntered) {
            app(App\Modules\Match\Services\FastModeService::class)->enter($game);
        }
        $advances = 0;
        $out = [];
        try {
            while ($advances < $maxBatches) {
                $game->refresh();
                $lastPlayed = (int) App\Models\GameMatch::where('game_id', $game->id)
                    ->where('played', true)->whereNull('cup_tie_id')->max('round_number');
                if ($lastPlayed >= $matchday) {
                    $out[] = "TARGET REACHED at matchday {$lastPlayed}";
                    break;
                }
                $hasMatches = App\Models\GameMatch::where('game_id', $game->id)
                    ->where('played', false)->exists();
                if (!$hasMatches) {
                    $out[] = 'NO MORE MATCHES — season complete?';
                    break;
                }
                $t0 = microtime(true);
                $ok = app(App\Modules\Match\Services\MatchdayAdvanceCoordinator::class)->runSync($game->id, fastForward: true);
                $dt = round(microtime(true) - $t0, 1);
                if (!$ok) {
                    $out[] = 'COULD NOT CLAIM advancing flag';
                    break;
                }
                $advances++;
                // drain career actions queue inline
                try {
                    Illuminate\Support\Facades\Artisan::call('queue:work', [
                        '--queue' => 'gameplay', '--stop-when-empty' => true, '--tries' => 1,
                    ]);
                } catch (\Throwable $e) {
                    $out[] = 'queue drain error: ' . $e->getMessage();
                }
                gc_collect_cycles();
                $game->refresh();
                $lastPlayed = (int) App\Models\GameMatch::where('game_id', $game->id)
                    ->where('played', true)->whereNull('cup_tie_id')->max('round_number');
                $out[] = "batch #{$advances} — matchday {$lastPlayed} — {$game->current_date->toDateString()} — {$dt}s";
                if (microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? $t0) > 200) {
                    $out[] = 'TIME BUDGET — stopping early';
                    break;
                }
            }
        } finally {
            if (!$fastModeWasEntered) {
                app(App\Modules\Match\Services\FastModeService::class)->exit($game->refresh());
            }
        }
        echo implode("\n", $out);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo 'SIMULATE FAIL: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
    }
    exit;
}

if ($step === 'errors') {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) {
        exit('no log file');
    }
    // Last ~400 lines, filter for ERROR/exception blocks
    $lines = array_slice(file($logFile), -400);
    $text = implode('', $lines);
    // Print only exception/error chunks (last 3)
    preg_match_all('/\[\d{4}-\d{2}-\d{2}.*? (ERROR|CRITICAL).*?(?=\[\d{4}-\d{2}-\d{2}|\z)/s', $text, $m);
    $chunks = array_slice($m[0], -3);
    if (!$chunks) {
        echo "NO ERRORS in last 400 log lines\n";
    } else {
        foreach ($chunks as $c) {
            echo substr($c, 0, 3000) . "\n---\n";
        }
    }
    exit;
}

if ($step === 'grantaccess') {
    $email = $_GET['email'] ?? '';
    $user = App\Models\User::where('email', $email)->first();
    if (!$user) {
        http_response_code(404);
        exit('user not found');
    }
    $user->update(['has_career_access' => true, 'has_tournament_access' => true]);
    echo "ACCESS GRANTED to {$email} (id {$user->id})";
    exit;
}

if ($step === 'gameinfo') {
    $gameId = $_GET['gameId'] ?? '';
    if (!$gameId) {
        http_response_code(400);
        exit('missing gameId');
    }
    $game = App\Models\Game::find($gameId);
    if (!$game) {
        http_response_code(404);
        exit('game not found');
    }
    $played = App\Models\GameMatch::where('game_id', $gameId)->where('played', true)->count();
    $pending = App\Models\GameMatch::where('game_id', $gameId)->where('played', false)->count();
    $linked = App\Models\Game::where('linked_game_id', $gameId)->pluck('id')->all();
    echo json_encode([
        'team' => $game->team->name ?? '?',
        'current_date' => $game->current_date->toDateString(),
        'played' => $played,
        'pending' => $pending,
        'linked_games' => $linked,
    ], JSON_PRETTY_PRINT);
    exit;
}

http_response_code(400);
echo 'unknown step';
