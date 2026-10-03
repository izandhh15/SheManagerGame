<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resilience against transient DB outages.
 *
 * If the request dies with a *connection-level* DB error (server gone away,
 * timeout, SSL syscall…), reconnect once and retry the request a single time
 * before giving up. Query-level errors (bad SQL, constraint violations) are
 * NOT retried.
 */
class RetryOnDbFailure
{
    /**
     * HTTP methods that are safe to retry: re-executing them cannot apply a
     * side effect twice. Non-idempotent methods (POST/PUT/PATCH/DELETE) are
     * never retried — if the connection drops after the commit but before
     * the response is read, a blind retry would duplicate a transfer,
     * purchase or spend.
     */
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (QueryException $e) {
            if (! self::isConnectionFailure($e)) {
                throw $e;
            }

            if (! in_array($request->method(), self::IDEMPOTENT_METHODS, true)) {
                // Non-idempotent request: the failure may have happened after
                // the handler committed its writes, and the middleware cannot
                // distinguish that case — never replay it.
                throw $e;
            }

            // One retry with a fresh connection.
            try {
                DB::reconnect();
            } catch (\Throwable) {
                // Reconnect itself failed; fall through to the friendly 503.
            }

            try {
                return $next($request);
            } catch (QueryException $retryEx) {
                // Still failing: let the exception handler render the
                // friendly "try again in a moment" page.
                throw $retryEx;
            }
        }
    }

    public static function isConnectionFailure(QueryException $e): bool
    {
        $sqlState = $e->getPrevious()?->getCode();
        $message = strtolower($e->getMessage());

        $connectionStates = ['08006', '08001', '08004', '57P01', '57P02', '57P03'];
        if (in_array((string) $sqlState, $connectionStates, true)) {
            return true;
        }

        $needles = [
            'could not receive data from server',
            'server closed the connection unexpectedly',
            'ssl syscall error',
            'operation timed out',
            'connection timed out',
            'no connection to the server',
            'gone away',
        ];
        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
