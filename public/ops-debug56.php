<?php
// Temporary ops endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'TEMP_OPS_20260930_DEBUG56') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');

try {
    // Find the stuck game (most recent without setup_completed_at)
    $game = \App\Models\Game::whereNull('setup_completed_at')
        ->whereNull('deleting_at')
        ->orderBy('created_at', 'desc')
        ->first();
    
    if (!$game) {
        echo "No stuck games found.\n";
        exit;
    }
    
    echo "Game ID: {$game->id}\n";
    echo "Team: {$game->team_id}\n";
    echo "Competition: {$game->competition_id}\n";
    echo "Step: {$game->season_transition_step}\n";
    echo "Created: {$game->created_at}\n\n";
    
    // Try running the DefaultInvestmentProcessor manually
    echo "Testing DefaultInvestmentProcessor...\n";
    $processor = app(\App\Modules\Season\Processors\DefaultInvestmentProcessor::class);
    $data = new \App\Modules\Season\DTOs\SeasonTransitionData(
        oldSeason: '0',
        newSeason: '2026',
        competitionId: $game->competition_id,
        isInitialSeason: true,
    );
    
    $result = $processor->process($game, $data);
    echo "SUCCESS: DefaultInvestmentProcessor completed.\n";
    
} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
