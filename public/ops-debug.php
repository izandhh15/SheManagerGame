<?php
// TEMPORAL: diagnosticar el 500 en POST /login. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

$out = [];
$out[] = 'php=' . PHP_VERSION . ' intl=' . (extension_loaded('intl') ? 1 : 0);

try {
    $k = Str::transliterate(Str::lower('test@example.com') . '|127.0.0.1');
    $out[] = "transliterate OK: $k";
} catch (Throwable $e) {
    $out[] = 'transliterate FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 200);
}

try {
    $hits = RateLimiter::hit('dbg-login-test-key');
    $out[] = "RateLimiter::hit OK: hits=$hits";
    RateLimiter::clear('dbg-login-test-key');
} catch (Throwable $e) {
    $out[] = 'RateLimiter::hit FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 200);
}

try {
    $ok = Auth::attempt(['email' => 'nadie@ejemplo.com', 'password' => 'wrongpassword123']);
    $out[] = 'Auth::attempt (bad creds) OK: ' . var_export($ok, true);
} catch (Throwable $e) {
    $out[] = 'Auth::attempt FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 200);
}

try {
    $v = Cache::get('dbg-cache-test', 'default-val');
    $out[] = "Cache::get OK: $v";
} catch (Throwable $e) {
    $out[] = 'Cache::get FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 200);
}

try {
    $msg = trans('auth.failed');
    $out[] = "trans(auth.failed) OK: $msg";
} catch (Throwable $e) {
    $out[] = 'trans FAIL: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 200);
}

echo implode("\n", $out) . "\n";
