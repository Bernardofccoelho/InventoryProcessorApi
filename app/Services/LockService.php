<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class LockService
{
    private const int LOCK_DURATION = 10;

    private const int WAIT_TIMEOUT = 3;

    public function acquire(string $key): Lock
    {
        $store = config('cache.lock_store', 'redis');

        $lock = Cache::store($store)->lock("stock-lock:{$key}", self::LOCK_DURATION);

        $lock->block(self::WAIT_TIMEOUT);

        return $lock;
    }

    public function release(?Lock $lock): void
    {
        if ($lock !== null) {
            try {
                $lock->release();
            } catch (\Throwable $e) {
            }
        }
    }
}
