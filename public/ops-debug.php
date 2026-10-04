<?php
// TEMPORAL v4: Eloquent INSERT sin transaccion. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

$out = [];

try {
    $u = User::create([
        'name' => 'DbgUser4',
        'email' => 'dbg4_' . time() . '@example.com',
        'password' => Hash::make('Password123!'),
    ]);
    $out[] = "User::create sin transaccion OK: id=" . $u->id;
    
    // Ahora probar un UPDATE en una transaccion nueva
    $r = DB::transaction(function () use ($u) {
        return DB::table('users')->where('id', $u->id)->update(['name' => 'DbgUser4b']);
    });
    $out[] = "UPDATE en transaccion despues OK: $r";
    
    $u->forceDelete();
    $out[] = "cleanup OK";
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 300);
}

echo implode("\n", $out) . "\n";
