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

$step = $_GET['step'] ?? 'find';

try {
    if ($step === 'create') {
        // Create a test game for Valencia in ESP1
        $user = \App\Models\User::where('email', 'test-visual-2957@example.com')->first();
        if (!$user) {
            echo "Test user not found.\n";
            exit;
        }
        
        echo "Looking for ESP1 teams...\n";
        $teams = \App\Models\Team::whereHas('competitions', function($q) {
                $q->where('competitions.id', 'ESP1');
            })
            ->limit(5)
            ->get(['id', 'name']);
        
        foreach ($teams as $t) {
            echo "  - {$t->name} ({$t->id})\n";
        }
        
        $team = $teams->first();
        
        if (!$team) {
            echo "Valencia team not found.\n";
            exit;
        }
        
        echo "Creating game for team: {$team->name} ({$team->id})\n";
        
        $service = app(\App\Modules\Season\Services\GameCreationService::class);
        $game = $service->create(
            userId: $user->id,
            teamId: $team->id,
            gameMode: \App\Models\Game::MODE_CAREER,
        );
        
        echo "Game created: {$game->id}\n";
        echo "Now run step=test with game_id={$game->id}\n";
        exit;
    }
    
    if ($step === 'test') {
        $gameId = $_GET['game_id'] ?? '';
        $game = \App\Models\Game::find($gameId);
        
        if (!$game) {
            echo "Game not found.\n";
            exit;
        }
        
        echo "Testing setup for game: {$game->id}\n";
        echo "Current step: {$game->season_transition_step}\n\n";
        
        // Run the full SetupNewGame job synchronously
        echo "Running SetupNewGame job...\n";
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
                app(\App\Services\FormationRecommender::class),
                app(\App\Services\FormationBiasResolver::class),
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
