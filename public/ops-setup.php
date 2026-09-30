<?php

/**
 * TEMPORAL — solo para el setup inicial de la beta en Wasmer.
 * BORRAR tras ejecutar. Se protege con el secreto OPS_TOKEN.
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

function run(string $cmd): void
{
    echo "\$ $cmd\n";
    $code = 0;
    passthru("cd /app && php artisan $cmd 2>&1", $code);
    echo "\n[exit code: $code]\n---\n";
}

if ($step === 'diag') {
    echo 'cwd: ' . getcwd() . "\n";
    echo 'php: ' . PHP_VERSION . "\n";
    echo '--- ls ---' . "\n";
    passthru('ls -la 2>&1 | head -20');
    echo '--- /app ---' . "\n";
    passthru('ls -la /app 2>&1 | head -20');
    echo '--- which php ---' . "\n";
    passthru('command -v php; which php 2>&1');
    exit;
}

switch ($step) {
    case 'migrate':
        run('migrate --force');
        break;
    case 'seed':
        // Datos base: clubs, competiciones, plantillas (temporada 2026)
        run('app:seed-reference-data --fresh');
        break;
    case 'seed-nt':
        // Selecciones nacionales + WWCQ (beta)
        run('app:seed-national-teams --fresh');
        break;
    default:
        echo "steps: migrate, seed, seed-nt\n";
}
