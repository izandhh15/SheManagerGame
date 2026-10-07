<?php
// TEMPORAL: perfilar SelectTeam. Borrar tras usar.
set_time_limit(120);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Competition;
use App\Models\Team;
use App\Modules\Competition\Services\CountryConfig;

$out = [];
$t0 = microtime(true);

function lap($label) {
    global $out, $t0;
    static $last = null;
    $now = microtime(true);
    if ($last === null) $last = $t0;
    $out[] = sprintf('%s: +%.0fms (total %.0fms)', $label, ($now-$last)*1000, ($now-$t0)*1000);
    $last = $now;
}

// 1. Cache check
$t = microtime(true);
$cached = Cache::get('career_mode_countries:v6');
lap('cache get career_mode_countries: ' . ($cached ? 'HIT' : 'MISS'));

// 2. Si es MISS, probar una sola competición
if (!$cached) {
    $cc = app(CountryConfig::class);
    $codes = $cc->playableCountryCodes();
    lap('playableCountryCodes: ' . count($codes) . ' paises');
    
    $t = microtime(true);
    $comp = Competition::with('teams')->find('ESP1');
    lap('Competition::with(teams)->find(ESP1): ' . ($comp ? $comp->teams->count() . ' teams' : 'null'));
}

echo implode("\n", $out) . "\n";
