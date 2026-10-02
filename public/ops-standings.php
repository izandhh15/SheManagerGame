<?php
// TEMPORARY — DELETE AFTER USE
if (($_GET['token'] ?? '') !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;

$gameId = $_GET['game'] ?? '';
$game = Game::find($gameId);
if (!$game) { exit("no game\n"); }
echo "Game: {$game->id} | team_id: {$game->team_id} | comp: {$game->competition_id} | date: {$game->current_date}\n";
echo "Season complete flags — needs_new_season_setup: ".($game->needs_new_season_setup?'1':'0')."\n\n";

$standings = GameStanding::where('game_id', $gameId)
    ->where('competition_id', $game->competition_id)
    ->orderBy('position')
    ->get();
foreach ($standings as $s) {
    $t = Team::find($s->team_id);
    $mark = $s->team_id === $game->team_id ? ' <== GETAFE' : '';
    echo "#{$s->position} {$t->name} — {$s->points} pts ({$s->won}V {$s->drawn}E {$s->lost}D){$mark}\n";
}
echo "\nCup ties (playoff):\n";
$ties = App\Models\CupTie::where('game_id', $gameId)->orderBy('round_number')->get();
foreach ($ties as $tie) {
    $h = Team::find($tie->home_team_id)?->name ?? '?';
    $a = Team::find($tie->away_team_id)?->name ?? '?';
    $w = $tie->winner_id ? (Team::find($tie->winner_id)?->name ?? '?') : 'pendiente';
    echo "R{$tie->round_number} [{$tie->competition_id}] $h vs $a — ganador: $w\n";
}
