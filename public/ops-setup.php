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
    case 'check':
        echo 'competitions WQ*: '.$db::table('competitions')->where('id', 'like', 'WQ%')->count()."\n";
        foreach (['WQUEFA','WQAFC','WQCAF','WQCONC','WQCONM','WQOFC'] as $id) {
            $n = $db::table('competition_teams')->where('competition_id', $id)->count();
            echo "  $id teams: $n\n";
        }
        echo 'national teams with confederation: '.$db::table('teams')->where('type', 'national')->whereNotNull('confederation')->count()."\n";
        echo 'national teams total: '.$db::table('teams')->where('type', 'national')->count()."\n";
        $sui = $db::table('teams')->where('id', 'SUI1')->first();
        if ($sui) {
            echo "SUI1 competition found\n";
        } else {
            echo "SUI1 competition NOT found\n";
        }
        $luz = $db::table('teams')->where('transfermarkt_id', '2224')->first();
        echo 'Luzern (tmId 2224): '.($luz ? $luz->name : 'NOT FOUND')."\n";
        $zrh = $db::table('teams')->where('transfermarkt_id', '576')->first();
        echo 'Zurich (tmId 576): '.($zrh ? $zrh->name : 'NOT FOUND')."\n";
        echo 'users with career access: '.$db::table('users')->where('has_career_access', true)->count()."\n";
        echo 'games.linked_game_id column: '.(Illuminate\Support\Facades\Schema::hasColumn('games', 'linked_game_id') ? 'yes' : 'no')."\n";
        echo 'teams.confederation column: '.(Illuminate\Support\Facades\Schema::hasColumn('teams', 'confederation') ? 'yes' : 'no')."\n";
        echo "---\n";
        break;
    default:
        echo "steps: migrate | seed-nt | seed-ch | grant-career | check\n---\n";
}
