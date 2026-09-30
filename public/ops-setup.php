<?php
// Temporary ops endpoint - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'TEMP_OPS_20260930_WNL') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$step = $_GET['step'] ?? 'info';
header('Content-Type: text/plain');

try {
    switch ($step) {
        case 'info':
            echo "PHP: " . PHP_VERSION . "\n";
            echo "DB: " . config('database.default') . "\n";
            break;
            
        case 'migrate':
            echo "Running migrations...\n";
            Artisan::call('migrate', ['--force' => true]);
            echo Artisan::output();
            break;
            
        case 'seed-wnl':
            echo "Seeding WNL competition...\n";
            // Seed just the WNL competition row
            DB::table('competitions')->updateOrInsert(
                ['id' => 'WNL'],
                [
                    'name' => "UEFA Women's Nations League",
                    'country' => 'IN',
                    'flag' => null,
                    'tier' => 1,
                    'type' => 'league',
                    'role' => 'league',
                    'scope' => 'continental',
                    'handler_type' => 'league',
                    'season' => '2026',
                ]
            );
            echo "WNL competition seeded.\n";
            
            // Verify WNL groups.json is readable
            $groupsPath = base_path('data/2026/WNL/groups.json');
            if (file_exists($groupsPath)) {
                $data = json_decode(file_get_contents($groupsPath), true);
                echo "Groups: " . count($data['groups'] ?? []) . "\n";
                foreach ($data['groups'] ?? [] as $gid => $g) {
                    echo "  $gid: " . implode(', ', $g['teams']) . "\n";
                }
            } else {
                echo "WARNING: groups.json not found!\n";
            }
            break;
            
        case 'check':
            echo "WNL competition: ";
            $wnl = DB::table('competitions')->where('id', 'WNL')->first();
            echo $wnl ? "EXISTS ({$wnl->name})\n" : "MISSING\n";
            
            echo "WNL config class: ";
            echo class_exists('App\Modules\Competition\Configs\WomensNationsLeagueConfig') ? "EXISTS\n" : "MISSING\n";
            break;
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
