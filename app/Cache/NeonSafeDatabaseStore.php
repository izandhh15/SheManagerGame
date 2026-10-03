<?php

namespace App\Cache;

use Closure;
use Illuminate\Cache\DatabaseStore;

/**
 * Database cache store that avoids SELECT ... FOR UPDATE.
 *
 * The Neon pooler (PgBouncer in transaction mode) silently aborts the
 * transaction when a SELECT ... FOR UPDATE runs inside it: the SELECT
 * itself returns rows without throwing, but every subsequent statement in
 * the transaction fails with SQLSTATE[25P02] ("current transaction is
 * aborted"). Laravel's stock DatabaseStore::incrementOrDecrement() wraps
 * its read-modify-write in a transaction with lockForUpdate(), so any
 * Cache::increment() — e.g. the login RateLimiter — 500s on this pooler.
 *
 * This store does the read-modify-write without locks or explicit
 * transactions. For rate-limiter counters a lost update under extreme
 * concurrency is acceptable (it's a throttle, not accounting).
 */
class NeonSafeDatabaseStore extends DatabaseStore
{
    protected function incrementOrDecrement($key, $value, Closure $callback)
    {
        $prefixed = $this->getPrefix().$key;

        $cache = $this->table()->where('key', $prefixed)->first();

        if (is_null($cache)) {
            return false;
        }

        $cache = is_array($cache) ? (object) $cache : $cache;

        $current = $this->unserialize($cache->value);

        if (! is_numeric($current)) {
            return false;
        }

        $new = $callback((int) $current, $value);

        $this->table()->where('key', $prefixed)->update([
            'value' => $this->serialize($new),
        ]);

        return $new;
    }
}
