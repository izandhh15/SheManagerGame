<?php
// Temporary ops endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'DBG56B_20261001') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');
set_time_limit(600);

$step = $_GET['step'] ?? 'code';

try {
    if ($step === 'code') {
        $path = base_path('app/Modules/Competition/Services/CupDrawService.php');
        echo "sha1: " . sha1_file($path) . "\n";
        echo "mtime: " . date('c', filemtime($path)) . "\n";
        $code = file_get_contents($path);
        echo "has_bye_fix: " . (str_contains($code, 'Bye: [teamId, null]') ? 'YES' : 'NO') . "\n";
        echo "throws_odd: " . (str_contains($code, 'throw OddCupDrawPoolException') ? 'YES' : 'NO') . "\n";
        exit;
    }

    if ($step === 'create') {
        $user = \App\Models\User::where('email', 'test-pingu-20260930@example.com')->first();
        if (!$user) { echo "Test user not found.\n"; exit; }
        $team = \App\Models\Team::whereHas('competitions', fn($q) => $q->where('competitions.id', 'ESP1'))
            ->where('name', 'like', '%Valencia%')
            ->first(['id', 'name']);
        if (!$team) {
            $team = \App\Models\Team::whereHas('competitions', fn($q) => $q->where('competitions.id', 'ESP1'))->first(['id', 'name']);
        }
        echo "Team: {$team->name} ({$team->id})\n";
        $service = app(\App\Modules\Season\Services\GameCreationService::class);
        $game = $service->create(userId: (string) $user->id, teamId: $team->id, gameMode: \App\Models\Game::MODE_CAREER);
        echo "Game created: {$game->id}\n";
        exit;
    }

    if ($step === 'test') {
        $gameId = $_GET['game_id'] ?? '';
        $game = \App\Models\Game::find($gameId);
        if (!$game) { echo "Game not found.\n"; exit; }
        echo "Testing setup for game: {$game->id}\n\n";
        try {
            $job = new \App\Modules\Season\Jobs\SetupNewGame(
                gameId: $game->id,
                teamId: $game->team_id,
                competitionId: $game->competition_id,
                season: '2026',
                gameMode: $game->game_mode,
            );
            $job->handle(
                app(\App\Modules\Season\Services\SeasonSetupPipeline::class),
                app(\App\Modules\Season\Processors\LeagueFixtureProcessor::class),
                app(\App\Modules\Season\Processors\StandingsResetProcessor::class),
                app(\App\Modules\Lineup\Services\FormationRecommender::class),
                app(\App\Modules\Lineup\Services\FormationBiasResolver::class),
            );
            echo "SUCCESS: SetupNewGame completed!\n";
        } catch (\Throwable $e) {
            echo "ERROR: " . get_class($e) . "\n";
            echo "Message: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            echo "Trace:\n" . $e->getTraceAsString() . "\n";
        }
        exit;
    }

    if ($step === 'verify') {
        $gameId = $_GET['game_id'] ?? '';
        $ties = \App\Models\CupTie::where('game_id', $gameId)
            ->where('competition_id', 'ESPCUP')
            ->orderBy('bracket_position')
            ->get(['bracket_position', 'home_team_id', 'away_team_id', 'completed', 'winner_id']);
        echo "ESPCUP ties: " . $ties->count() . "\n";
        foreach ($ties as $t) {
            $bye = $t->home_team_id === $t->away_team_id ? ' [BYE]' : '';
            echo "pos {$t->bracket_position}: home={$t->home_team_id} away={$t->away_team_id} completed=" . ($t->completed ? '1' : '0') . " winner={$t->winner_id}{$bye}\n";
        }
        exit;
    }

    if ($step === 'cleanup') {
        $gameId = $_GET['game_id'] ?? '';
        $game = \App\Models\Game::find($gameId);
        if ($game) { $game->delete(); echo "Deleted {$gameId}\n"; }
        else { echo "Not found.\n"; }
        exit;
    }

    echo "Unknown step.\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo "FATAL: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
