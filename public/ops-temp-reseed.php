<?php
// Temporary endpoint for re-seeding after Arratia/Murcia updates
// DELETE AFTER USE

if (($_GET['t'] ?? '') !== 'temp-reseed-01102026') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$step = $_GET['step'] ?? '';

if ($step === 'refresh') {
    $exit = Artisan::call('app:refresh-player-templates', ['--season' => '2026']);
    $output = Artisan::output();
    
    $before = DB::table('game_player_templates')->where('season', 2026)->count();
    
    echo json_encode([
        'artisan_exit' => $exit,
        'artisan_output' => $output,
        'templates_after' => $before,
    ]);
} elseif ($step === 'patch-games') {
    // Patch existing games with new templates (bulk)
    $games = DB::table('games')->select('id')->get();
    $count = 0;
    
    foreach ($games as $game) {
        // Get templates not yet in this game
        $existing = DB::table('game_players')
            ->where('game_id', $game->id)
            ->pluck('player_id')
            ->toArray();
        
        $templates = DB::table('game_player_templates')
            ->where('season', 2026)
            ->whereNotIn('player_id', $existing)
            ->get();
        
        foreach ($templates as $t) {
            DB::table('game_players')->insert([
                'id' => Illuminate\Support\Str::uuid(),
                'game_id' => $game->id,
                'player_id' => $t->player_id,
                'team_id' => $t->team_id,
                'name' => $t->name,
                'position' => $t->position,
                'overall_score' => $t->overall_score,
                'potential' => $t->potential,
                'date_of_birth' => $t->date_of_birth,
                'nationality' => $t->nationality,
                'market_value_cents' => $t->market_value_cents,
                'tier' => $t->tier,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }
    }
    
    echo json_encode(['games_patched' => count($games), 'players_added' => $count]);
} else {
    echo json_encode(['error' => 'Use ?step=refresh or ?step=patch-games']);
}
