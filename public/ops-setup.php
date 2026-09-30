<?php
// Temporary ops endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'OPS_TEMP_20260930') {
    http_response_code(403);
    die('Forbidden');
}

$action = $_GET['action'] ?? '';

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');

try {
    switch ($action) {
        case 'seed-ar':
            // Clear old templates for ARG1 teams (by name match)
            $arg1Names = ['Boca Juniors', 'River Plate', 'San Lorenzo', 'Racing Club', 'Belgrano', 'Gimnasia', 'Banfield', "Newell's", 'Huracán', 'Independiente', 'San Luis FC', 'Ferro', 'SAT', 'Unión', 'Lanús', 'Talleres'];
            \DB::table('game_player_templates')
                ->where('season', '2026')
                ->whereIn('team_id', function($q) use ($arg1Names) {
                    $q->select('id')->from('teams')->whereIn('name', $arg1Names);
                })->delete();
            $exit = $kernel->call('app:seed-reference-data', ['--country' => 'AR']);
            echo "AR seed exit: $exit\n";
            echo $kernel->output();
            break;
        case 'seed-br':
            $exit = $kernel->call('app:seed-reference-data', ['--country' => 'BR']);
            echo "BR seed exit: $exit\n";
            break;
        case 'seed-mx':
            $exit = $kernel->call('app:seed-reference-data', ['--country' => 'MX']);
            echo "MX seed exit: $exit\n";
            break;
        case 'seed-us':
            $exit = $kernel->call('app:seed-reference-data', ['--country' => 'US']);
            echo "US seed exit: $exit\n";
            break;
        case 'test-game':
            // Try to simulate game creation for a team and capture the error
            $teamId = $_GET['team_id'] ?? null;
            if (!$teamId) {
                echo "Usage: ?action=test-game&team_id=UUID\n";
                break;
            }
            try {
                $team = \App\Models\Team::find($teamId);
                if (!$team) {
                    echo "Team not found\n";
                    break;
                }
                echo "Team: {$team->name}\n";
                // Try to get the competition
                $comp = \DB::table('competitions')->where('id', $team->competition_id)->first();
                echo "Competition: " . ($comp ? $comp->name : 'NOT FOUND') . "\n";
            } catch (\Throwable $e) {
                echo "EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
                echo $e->getTraceAsString() . "\n";
            }
            break;
        case 'invite':
            $code = 'TEST-' . strtoupper(substr(md5(time()), 0, 8));
            \App\Models\InviteCode::create([
                'code' => $code,
                'max_uses' => 10,
                'times_used' => 0,
                'grants_career' => true,
                'grants_tournament' => true,
                'expires_at' => now()->addDays(7),
            ]);
            echo "Invite code: $code\n";
            break;
        case 'migrate':
            try {
                $exit = $kernel->call('migrate', ['--force' => true]);
                echo "Migrate exit: $exit\n";
                echo "OUTPUT:\n" . $kernel->output() . "\n";
            } catch (\Throwable $e) {
                echo "EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
            }
            break;
        case 'seed-lib':
            try {
                $exit = $kernel->call('app:seed-reference-data', ['--country' => 'AR']);
                echo "AR seed exit: $exit\n";
                echo "OUTPUT:\n" . $kernel->output() . "\n";
            } catch (\Throwable $e) {
                echo "EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
                echo $e->getTraceAsString() . "\n";
            }
            break;
        case 'seed-conca':
            try {
                $exit = $kernel->call('app:seed-reference-data', ['--country' => 'MX']);
                echo "MX seed exit: $exit\n";
                echo "OUTPUT:\n" . $kernel->output() . "\n";
            } catch (\Throwable $e) {
                echo "EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
            }
            break;
        case 'count':
            $teams = DB::table('teams')->count();
            $templates = DB::table('game_player_templates')->count();
            $comps = DB::table('competitions')->count();
            echo "Teams: $teams, Templates: $templates, Competitions: $comps\n";
            break;
        case 'list':
            $rows = DB::table('competitions')->orderBy('id')->get(['id', 'country', 'name', 'type']);
            foreach ($rows as $c) {
                echo "{$c->id} | {$c->country} | {$c->name} | {$c->type}\n";
            }
            break;
        default:
            echo "Actions: seed-ar, seed-br, seed-mx, seed-us, seed-lib, seed-conca, count\n";
    }
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n".$e->getTraceAsString();
}
