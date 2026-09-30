<?php

/**
 * TEMPORAL — solo para el deploy del 30-09-2026 en Wasmer (prod shemanager).
 * BORRAR tras ejecutar. Se protege con el secreto OPS_TOKEN.
 *
 * Uso: https://shemanager.wasmer.app/ops-setup.php?token=SECRETO&step=migrate
 */

$expected = getenv('OPS_TOKEN');
$given = $_GET['token'] ?? '';

if (!$expected || !hash_equals($expected, $given)) {
    http_response_code(403);
    exit('forbidden');
}

ignore_user_abort(true);
set_time_limit(0);

$step = $_GET['step'] ?? '';

header('Content-Type: text/plain; charset=utf-8');

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function run(string $command, array $args = []): void
{
    echo "\$ artisan $command\n";
    flush();
    try {
        $code = Illuminate\Support\Facades\Artisan::call($command, $args);
        echo Illuminate\Support\Facades\Artisan::output();
        echo "[exit code: $code]\n";
    } catch (Throwable $e) {
        echo 'ERROR: '.get_class($e).': '.$e->getMessage()."\n";
        echo $e->getTraceAsString()."\n";
    }
    echo "---\n";
    flush();
}

$db = Illuminate\Support\Facades\DB::class;

switch ($step) {
    case 'migrate':
        run('migrate', ['--force' => true]);
        break;
    case 'seed-nt':
        // Selecciones: 6 competiciones por confederación + backfill de confederation.
        // SIN --fresh: no borra nada.
        run('app:seed-national-teams');
        break;
    case 'seed-ch':
        // Suiza: nombres corregidos + Luzern 6381→2224. SIN --fresh.
        run('app:seed-reference-data', ['--country' => 'CH']);
        break;
    case 'grant-career':
        // Acceso al modo club: todos los usuarios existentes + el código de Izan.
        $u = $db::table('users')->where('has_career_access', false)->update(['has_career_access' => true]);
        echo "users granted career access: $u\n";
        $c = $db::table('invite_codes')->where('code', 'SHE-C1D924A7')->update(['grants_career' => true]);
        echo "invite SHE-C1D924A7 grants_career updated: $c\n---\n";
        break;
    case 'fix-ch':
        // Nombres corregidos (el seeder no actualiza 'name' en equipos existentes)
        $renames = [
            '576'  => 'FC Zürich',
            '157'  => 'Grasshopper Club Zürich',
            '557'  => 'FC Basel 1893',
            '434'  => 'Yverdon Sport FC',
            '119'  => 'FC Rapperswil-Jona',
            '1759' => 'FC St. Gallen 1879',
        ];
        foreach ($renames as $tmId => $name) {
            $n = $db::table('teams')->where('transfermarkt_id', $tmId)->update(['name' => $name]);
            echo "tmId $tmId -> '$name': $n updated\n";
        }
        // Luzern: el seed creó un duplicado con 2224; el viejo (6381) sobra
        $old = $db::table('teams')->where('transfermarkt_id', '6381')->first();
        $new = $db::table('teams')->where('transfermarkt_id', '2224')->first();
        echo 'old Luzern (6381): '.($old ? $old->id.' | '.$old->name : 'not found')."\n";
        echo 'new Luzern (2224): '.($new ? $new->id.' | '.$new->name : 'not found')."\n";
        if ($old && $new) {
            $db::table('competition_teams')->where('team_id', $old->id)->delete();
            $t = $db::table('game_player_templates')->where('team_id', $old->id)->delete();
            echo "deleted $t old templates + links\n";
            $db::table('teams')->where('id', $old->id)->delete();
            echo "deleted old Luzern team\n";
        } elseif ($old && !$new) {
            $db::table('teams')->where('id', $old->id)->update(['transfermarkt_id' => '2224', 'name' => 'FC Luzern']);
            echo "updated old Luzern in place\n";
        }
        if ($new) {
            $db::table('teams')->where('id', $new->id)->update(['name' => 'FC Luzern']);
        }
        echo "---\n";
        break;
    case 'check':
        echo 'competitions WQ*: '.$db::table('competitions')->where('id', 'like', 'WQ%')->count()."\n";
        foreach (['WQUEFA','WQAFC','WQCAF','WQCONC','WQCONM','WQOFC'] as $id) {
            $n = $db::table('competition_teams')->where('competition_id', $id)->count();
            echo "  $id teams: $n\n";
        }
        echo 'national teams with confederation: '.$db::table('teams')->where('type', 'national')->whereNotNull('confederation')->count()."\n";
        try {
            $suiCount = $db::table('competition_teams')->where('competition_id', 'SUI1')->count();
            echo "SUI1 linked teams: $suiCount\n";
        } catch (Throwable $e) {
            echo 'SUI1 count ERROR: '.get_class($e).': '.$e->getMessage()."\n";
        }
        $names = $db::table('teams')->join('competition_teams', 'teams.id', '=', 'competition_teams.team_id')->where('competition_teams.competition_id', 'SUI1')->orderBy('teams.name')->pluck('teams.name');
        foreach ($names as $nm) { echo "  - $nm\n"; }
        echo 'users with career access: '.$db::table('users')->where('has_career_access', true)->count()."\n";
        echo 'games.linked_game_id column: '.(Illuminate\Support\Facades\Schema::hasColumn('games', 'linked_game_id') ? 'yes' : 'no')."\n";
        echo 'teams.confederation column: '.(Illuminate\Support\Facades\Schema::hasColumn('teams', 'confederation') ? 'yes' : 'no')."\n";
        echo "---\n";
        break;
    default:
        echo "steps: migrate | seed-nt | seed-ch | grant-career | check\n---\n";
}
