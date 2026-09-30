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
    default:
        echo "steps: migrate, seed, seed-nt\n";
}
