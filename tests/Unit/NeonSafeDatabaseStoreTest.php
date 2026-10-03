<?php

namespace Tests\Unit;

use App\Cache\NeonSafeDatabaseStore;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The Neon pooler aborts transactions running SELECT ... FOR UPDATE
 * (SQLSTATE[25P02]), which made stock DatabaseStore::increment() 500 —
 * breaking POST /login via the RateLimiter. NeonSafeDatabaseStore must
 * support the full increment/decrement contract without locks.
 */
class NeonSafeDatabaseStoreTest extends TestCase
{
    public function test_increment_and_decrement_round_trip(): void
    {
        $store = new NeonSafeDatabaseStore(
            $this->app['db']->connection(), 'cache', 'neon-test:'
        );
        $repo = Cache::repository($store);

        $repo->put('counter', 0, 60);

        $this->assertSame(1, $repo->increment('counter'));
        $this->assertSame(2, $repo->increment('counter'));
        $this->assertSame(3, $repo->increment('counter', 1));
        $this->assertSame(1, $repo->decrement('counter', 2));
        $this->assertSame(1, $repo->get('counter'));

        $repo->forget('counter');
    }

    public function test_increment_missing_key_returns_false(): void
    {
        $store = new NeonSafeDatabaseStore(
            $this->app['db']->connection(), 'cache', 'neon-test:'
        );
        $repo = Cache::repository($store);

        $this->assertFalse($repo->increment('no-such-key'));
    }

    public function test_increment_non_numeric_returns_false(): void
    {
        $store = new NeonSafeDatabaseStore(
            $this->app['db']->connection(), 'cache', 'neon-test:'
        );
        $repo = Cache::repository($store);

        $repo->put('str', 'not-a-number', 60);
        $this->assertFalse($repo->increment('str'));
        $repo->forget('str');
    }
}
