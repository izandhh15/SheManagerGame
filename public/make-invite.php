<?php
// TEMPORARY - delete after use
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

if (($request->query('token') ?? '') !== 'INVITE_TEMP_20260930') {
    abort(403);
}

$code = 'SHE-' . strtoupper(bin2hex(random_bytes(4)));
\App\Models\InviteCode::create([
    'code' => $code,
    'email' => null,
    'max_uses' => 100000,
    'times_used' => 0,
    'grants_career' => true,
    'grants_tournament' => true,
    'expires_at' => null,
]);
echo "CODE: $code\n";
echo "LINK: https://shemanager.wasmer.app/register?invite=$code\n";
