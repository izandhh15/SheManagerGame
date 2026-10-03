<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->booting(function () {
        $uuidPattern = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
        foreach (['gameId', 'matchId', 'playerId', 'offerId', 'reportId', 'teamId', 'presetId', 'notificationId', 'summaryId', 'auditId'] as $param) {
            Route::pattern($param, $uuidPattern);
        }
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Wasmer Edge terminates TLS at its own edge network and forwards plain
        // HTTP to the app; trust X-Forwarded-Proto/Host/Port/Prefix so URL::to(),
        // asset(), route() and the session cookie honour the original https
        // scheme. Deliberately NOT trusting X-Forwarded-For: with `at: '*'` any
        // client could forge that header and mint unlimited identities to evade
        // IP-based throttles (game creation, waitlist, webhooks...).
        // Request::ip() now resolves to REMOTE_ADDR — the direct TCP peer,
        // i.e. the platform's own edge proxy — so IP throttles are spoof-proof;
        // worst case they share one bucket instead of being bypassed entirely.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\TrackVisitors::class,
            \App\Http\Middleware\RetryOnDbFailure::class,
        ]);

        $middleware->alias([
            'game.owner' => \App\Http\Middleware\EnsureGameOwnership::class,
            'beta.invite' => \App\Http\Middleware\RequireInviteForRegistration::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'database.editor' => \App\Http\Middleware\EnsureDatabaseEditor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('auth.session_expired'),
                    'redirect' => route('login'),
                ], 419);
            }

            return redirect()->route('login')
                ->with('warning', __('auth.session_expired'));
        });

        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
            if (! \App\Http\Middleware\RetryOnDbFailure::isConnectionFailure($e)) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'La base de datos no responde. Reintenta en unos segundos.',
                ], 503);
            }

            return response()->view('errors.db-down', [], 503);
        });
    })->create();

// Wasmer Edge: el sistema de ficheros del paquete es de solo lectura.
// Redirige storage/ al volumen montado en /data cuando exista la variable
// de entorno WASMER_STORAGE_PATH (definida en app.yaml).
if ($wasmerStorage = getenv('WASMER_STORAGE_PATH')) {
    $app->useStoragePath($wasmerStorage);
    foreach (['app', 'app/public', 'framework/cache', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
        @mkdir($wasmerStorage.'/'.$dir, 0755, true);
    }
}

return $app;
