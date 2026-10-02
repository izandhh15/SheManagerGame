<?php
// TEMPORARY ops endpoint — DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'rs7Sim2026xK') { http_response_code(403); exit('no'); }

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\User;
use App\Models\Game;
use App\Modules\Season\Services\GameCreationService;
use App\Modules\Season\Services\TournamentCreationService;
use App\Modules\Season\Services\NationalSquadService;

$step = $_GET['step'] ?? 'find';

if ($step === 'find') {
    echo "=== Real Sociedad ===\n";
    $rs = Team::where('name', 'like', '%Real Sociedad%')
        ->where('type', '!=', 'national')
        ->get(['id', 'name', 'country', 'transfermarkt_id']);
    foreach ($rs as $t) echo "{$t->id} | {$t->name} | {$t->country} | tm={$t->transfermarkt_id}\n";

    echo "=== Spain NT ===\n";
    $es = Team::where('type', 'national')
        ->where(function($q){ $q->where('name','like','%Espa%')->orWhere('name','like','%Spain%'); })
        ->get(['id', 'name', 'country', 'confederation']);
    foreach ($es as $t) echo "{$t->id} | {$t->name} | {$t->country} | {$t->confederation}\n";
}

if ($step === 'create') {
    $clubId = $_GET['club'] ?? '';
    $ntId = $_GET['nt'] ?? '';
    if (!$clubId || !$ntId) exit("need ?club=ID&nt=ID\n");

    // Temp user
    $user = User::firstOrCreate(
        ['email' => 'sim-rs-20261002@example.com'],
        ['name' => 'Sim RS', 'password' => bcrypt('TempSim2026!x')]
    );
    echo "User: {$user->id}\n";

    $gcs = app(GameCreationService::class);
    $clubGame = $gcs->create((string)$user->id, $clubId, Game::MODE_CAREER);
    echo "Club game: {$clubGame->id} (setup: ".($clubGame->setup_completed_at ? 'done' : 'pending').")\n";

    // Provisional squad for Spain
    $playerIds = NationalSquadService::provisionalSquad($ntId, []);
    echo "Squad picked: ".count($playerIds)."\n";

    $tcs = app(TournamentCreationService::class);
    $ntGame = $tcs->create(
        (string)$user->id,
        $ntId,
        TournamentCreationService::competitionIdForConfederation('UEFA'),
        $playerIds
    );
    echo "NT game: {$ntGame->id}\n";

    $ntGame->update(['linked_game_id' => $clubGame->id]);
    echo "Linked.\n";
    echo "CLUB_GAME_ID={$clubGame->id}\n";
    echo "NT_GAME_ID={$ntGame->id}\n";
}
