<?php

/**
 * TEMPORAL — solo para el setup inicial de la beta en Wasmer.
 * BORRAR tras ejecutar. Se protege con el secreto OPS_TOKEN.
 *
 * El shell está capado en el runtime PHP de Wasmer (passthru no devuelve
 * nada), así que los comandos artisan se ejecutan en proceso con
 * Artisan::call().
 *
 * Uso: https://shemanager-beta.wasmer.app/ops-setup.php?token=SECRETO&step=migrate
 *      https://shemanager-beta.wasmer.app/ops-setup.php?token=SECRETO&step=seed
 *      https://shemanager-beta.wasmer.app/ops-setup.php?token=SECRETO&step=seed-nt
 */

$expected = getenv('OPS_TOKEN');
$given = $_GET['token'] ?? '';

if (!$expected || !hash_equals($expected, $given)) {
    http_response_code(403);
    exit('forbidden');
}

// El seed tarda minutos: que no lo mate ni el timeout ni el cliente.
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

switch ($step) {
    case 'migrate':
        run('migrate', ['--force' => true]);
        break;
    case 'seed':
        // Datos base: clubs, competiciones, plantillas (temporada 2026)
        run('app:seed-reference-data', ['--fresh' => true]);
        break;
    case 'seed-nt':
        // Selecciones nacionales + WWCQ (beta)
        run('app:seed-national-teams', ['--fresh' => true]);
        break;
    case 'invite':
        // Código de invitación de la beta (500 usos)
        $code = 'BETA-' . strtoupper(substr(md5('shemanager-beta' . date('Ymd')), 0, 8));
        Illuminate\Support\Facades\DB::table('invite_codes')->updateOrInsert(
            ['code' => $code],
            ['max_uses' => 500, 'times_used' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
        echo "Invite code: $code\n---\n";
        break;
    case 'logs':
        $files = glob(storage_path('logs/laravel-*.log')) ?: [];
        if (!$files) { echo "no log files\n---\n"; break; }
        rsort($files);
        echo "FILE: " . basename($files[0]) . "\n";
        echo substr(file_get_contents($files[0]), -6000) . "\n---\n";
        break;
    case 'check-nt':
        $db = Illuminate\Support\Facades\DB::class;
        $ntCount = $db::table('teams')->where('type', 'national')->count();
        echo "national teams: $ntCount\n";
        $esp = $db::table('teams')->where('type', 'national')->where('fifa_code', 'ESP')->first();
        if (!$esp) { echo "ESP not found\n---\n"; break; }
        echo "ESP id: {$esp->id}\n";
        $seasons = $db::table('game_player_templates')->select('season', $db::raw('count(*) as c'))->groupBy('season')->orderBy('season')->get();
        foreach ($seasons as $s) { echo "season '{$s->season}': {$s->c}\n"; }
        $c = $db::table('game_player_templates')->where('team_id', $esp->id)->count();
        echo "templates team_id=ESP id: $c\n";
        $c2 = $db::table('game_player_templates')->where('team_id', $esp->id)->where('season', '2026')->count();
        echo "templates ESP + season 2026: $c2\n";
        $sample = $db::table('game_player_templates')->where('team_id', $esp->id)->limit(3)->get(['player_id','name','season']);
        foreach ($sample as $row) { echo "  - {$row->player_id} {$row->name} season={$row->season}\n"; }
        echo "---\n";
        break;
    case 'debug500':
        // Simula una petición HTTP a / y muestra la excepción real
        try {
            $request = Illuminate\Http\Request::create('/', 'GET');
            $httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $response = $httpKernel->handle($request);
            echo "Status: " . $response->getStatusCode() . "\n";
            echo substr($response->getContent(), 0, 2000) . "\n---\n";
        } catch (Throwable $e) {
            echo 'ERROR: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n---\n";
        }
        break;
    default:
        echo "steps: migrate, seed, seed-nt\n";
}
