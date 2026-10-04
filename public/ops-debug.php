<?php
// TEMPORAL v2: aislar INSERT...RETURNING. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];
$conn = DB::connection();

// Test 1: INSERT...RETURNING en transaccion
try {
    $id = $conn->transaction(function () use ($conn) {
        $r = $conn->select('INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) RETURNING id', ['DbgX', 'dbg_x_'.time().'@example.com', 'hash']);
        return $r[0]->id;
    });
    $out[] = "INSERT...RETURNING en transaction OK: id=$id";
    DB::table('users')->where('id', $id)->delete();
} catch (Throwable $e) {
    $out[] = 'INSERT...RETURNING FAIL: ' . substr($e->getMessage(), 0, 250);
}

// Test 2: INSERT simple (sin RETURNING) en transaccion
try {
    $ok = $conn->transaction(function () use ($conn) {
        return $conn->insert('INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())', ['DbgY', 'dbg_y_'.time().'@example.com', 'hash']);
    });
    $out[] = "INSERT sin RETURNING OK: " . var_export($ok, true);
    DB::table('users')->where('email', 'like', 'dbg_y_%@example.com')->delete();
} catch (Throwable $e) {
    $out[] = 'INSERT sin RETURNING FAIL: ' . substr($e->getMessage(), 0, 250);
}

echo implode("\n", $out) . "\n";
