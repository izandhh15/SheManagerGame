<?php
/**
 * Temporary ops endpoint for running seeds on Wasmer (no SSH access).
 * DELETE THIS FILE after use.
 *
 * Usage: https://shemanager.wasmer.app/ops-setup.php?token=SECRET&step=seed-ar
 */

$token = $_GET['token'] ?? '';
$expected = getenv('OPS_TOKEN') ?: 'change-me-in-app-yaml';

if (!hash_equals($expected, $token)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$step = $_GET['step'] ?? '';

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

try {
    switch ($step) {
        case 'seed-ar':
            $exit = $kernel->call('app:seed-reference-data', ['--country' => 'AR']);
            echo "Seed AR exit: $exit\n";
            echo $kernel->output();
            break;

        case 'seed-br':
            $exit = $kernel->call('app:seed-reference-data', ['--country' => 'BR']);
            echo "Seed BR exit: $exit\n";
            echo $kernel->output();
            break;

        case 'seed-mx':
            $exit = $kernel->call('app:seed-reference-data', ['--country' => 'MX']);
            echo "Seed MX exit: $exit\n";
            echo $kernel->output();
            break;

        case 'seed-us':
            $exit = $kernel->call('app:seed-reference-data', ['--country' => 'US']);
            echo "Seed US exit: $exit\n";
            echo $kernel->output();
            break;

        case 'seed-profiles':
            $exit = $kernel->call('db:seed', ['--class' => 'ClubProfilesSeeder', '--force' => true]);
            echo "Seed profiles exit: $exit\n";
            echo $kernel->output();
            break;

        case 'migrate':
            $exit = $kernel->call('migrate', ['--force' => true]);
            echo "Migrate exit: $exit\n";
            echo $kernel->output();
            break;

        default:
            echo "Available steps: migrate, seed-ar, seed-br, seed-mx, seed-us, seed-profiles\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
