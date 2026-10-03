<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Http\Middleware\RetryOnDbFailure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * TRIAGE-B G16: RetryOnDbFailure re-executed $next($request) even for
 * POSTs — if the connection dropped AFTER the commit, the retry duplicated
 * a transfer/purchase/spend. Only idempotent methods (GET/HEAD/OPTIONS)
 * are retried now.
 */
class RetryOnDbFailureTest extends TestCase
{
    private function connectionFailure(): QueryException
    {
        return new QueryException(
            'pgsql',
            'select 1',
            [],
            new \Exception('server closed the connection unexpectedly')
        );
    }

    private function queryFailure(): QueryException
    {
        return new QueryException(
            'pgsql',
            'insert into x values (1)',
            [],
            new \Exception('duplicate key value violates unique constraint "x_pkey"')
        );
    }

    public function test_detects_connection_failures(): void
    {
        $this->assertTrue(RetryOnDbFailure::isConnectionFailure($this->connectionFailure()));
        $this->assertFalse(RetryOnDbFailure::isConnectionFailure($this->queryFailure()));
    }

    public function test_post_is_never_retried(): void
    {
        $middleware = new RetryOnDbFailure();
        $calls = 0;

        try {
            $middleware->handle(Request::create('/x', 'POST'), function () use (&$calls) {
                $calls++;
                throw $this->connectionFailure();
            });
            $this->fail('QueryException should have propagated without retry');
        } catch (QueryException) {
            $this->assertSame(1, $calls, 'POST handler must run exactly once');
        }
    }

    public function test_put_is_never_retried(): void
    {
        $middleware = new RetryOnDbFailure();
        $calls = 0;

        try {
            $middleware->handle(Request::create('/x', 'PUT'), function () use (&$calls) {
                $calls++;
                throw $this->connectionFailure();
            });
            $this->fail('QueryException should have propagated without retry');
        } catch (QueryException) {
            $this->assertSame(1, $calls, 'PUT handler must run exactly once');
        }
    }

    public function test_get_is_retried_once_then_succeeds(): void
    {
        $middleware = new RetryOnDbFailure();
        $calls = 0;

        $response = $middleware->handle(Request::create('/x', 'GET'), function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw $this->connectionFailure();
            }

            return response('ok');
        });

        $this->assertSame(2, $calls);
        $this->assertSame('ok', $response->getContent());
    }

    public function test_get_with_query_error_is_not_retried(): void
    {
        $middleware = new RetryOnDbFailure();
        $calls = 0;

        try {
            $middleware->handle(Request::create('/x', 'GET'), function () use (&$calls) {
                $calls++;
                throw $this->queryFailure();
            });
            $this->fail('QueryException should have propagated without retry');
        } catch (QueryException) {
            $this->assertSame(1, $calls, 'query-level errors must never retry');
        }
    }
}
