<?php
// TEMPORAL v5: Eloquent INSERT solo en transaccion. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

$out = [];

try {
    $id = DB::transaction(function () {
        $u = User::create([
            'name' => 'DbgUser5',
            'email' => 'dbg5_' . time() . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        return $u->id;
    });
    $out[] = "User::create EN transaccion OK: id=$id";
    User::where('id', $id)->forceDelete();
} catch (Throwable $e) {
    $out[] = 'FAIL: ' . substr($e->getMessage(), 0, 300);
}

// Y ahora: INSERT via query builder (no Eloquent) en transaccion + UPDATE
try {
    $r = DB::transaction(function () {
        $id = DB::table('users')->insertGetId([
            'name' => 'DbgUser6',
            'email' => 'dbg6_' . time() . '@example.com',
            'password' => Hash::make('Password123!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $n = DB::table('users')->where('id', $id)->update(['name' => 'DbgUser6b']);
        return "id=$id updated=$n";
    });
    $out[] = "Query builder insertGetId+update OK: $r";
    DB::table('users')->where('email', 'like', 'dbg6_%')->delete();
} catch (Throwable $e) {
    $out[] = 'QB FAIL: ' . substr($e->getMessage(), 0, 300);
}

echo implode("\n", $out) . "\n";
