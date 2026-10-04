<?php
// TEMPORAL v3: error completo del UPDATE. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

$out = [];

try {
    $user = DB::transaction(function () {
        $u = User::create([
            'name' => 'DbgUser3',
            'email' => 'dbg3_' . time() . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        $u->forceFill([
            'email_verified_at' => now(),
            'has_career_access' => true,
            'has_tournament_access' => true,
        ])->save();
        return $u;
    });
    $out[] = "OK: id=" . $user->id;
    $user->forceDelete();
} catch (Throwable $e) {
    $out[] = 'CLASS: ' . get_class($e);
    $out[] = 'MSG: ' . $e->getMessage();
    if (method_exists($e, 'getSql')) {
        $out[] = 'SQL: ' . $e->getSql();
        $out[] = 'BINDINGS: ' . json_encode($e->getBindings());
    }
    $prev = $e->getPrevious();
    if ($prev) {
        $out[] = 'PREV: ' . get_class($prev) . ': ' . $prev->getMessage();
    }
}

echo implode("\n", $out) . "\n";
