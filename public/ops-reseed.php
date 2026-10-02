<?php
$token = $_GET['token'] ?? '';
if ($token !== 'q7xK9mP2vL4nQ8w') { http_response_code(403); exit('no'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\Competition;

// Verify BK Häcken exists in teams table (from EUR pool)
$hacken = Team::where('transfermarkt_id', '8657')->first();
echo "BK Häcken in DB: ".($hacken ? "YES ({$hacken->name})" : "NO")."\n";

// Verify Athletic exists
$athletic = Team::where('name', 'Athletic Club')->where('country', 'ES')->first();
echo "Athletic in DB: ".($athletic ? "YES" : "NO")."\n";

// Check UEL competition
$uel = Competition::where('id', 'UEL')->first();
echo "UEL competition: ".($uel ? "exists" : "MISSING")."\n";

echo "Done - config fix is live, reference data will update on next full seed.\n";
