<?php
// TEMPORAL: diagnosticar 500 en POST /register/career. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

$out = [];

// Paso 1: validacion unique email (como en el controlador)
try {
    $exists = User::where('email', 'testreg12345@example.com')->exists();
    $out[] = "unique check OK: " . ($exists ? 'exists' : 'not exists');
} catch (Throwable $e) {
    $out[] = 'unique check FAIL: ' . substr($e->getMessage(), 0, 200);
}

// Paso 2: DB::transaction con User::create (como en el controlador)
try {
    $user = DB::transaction(function () {
        $u = User::create([
            'name' => 'DbgUser',
            'email' => 'dbg_' . time() . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        $u->forceFill([
            'email_verified_at' => now(),
            'has_career_access' => true,
            'has_tournament_access' => true,
        ])->save();
        return $u;
    });
    $out[] = "transaction+create OK: id=" . $user->id;
    // limpiar
    $user->forceDelete();
    $out[] = "cleanup OK";
} catch (Throwable $e) {
    $out[] = 'transaction+create FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 300);
}

echo implode("\n", $out) . "\n";
