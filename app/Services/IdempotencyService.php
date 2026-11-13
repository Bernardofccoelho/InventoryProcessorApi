<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

final class IdempotencyService
{
    public function __construct(private readonly string $key) {}

    private const string EVENT_ID_SUFFIX = '_event_id';

    public function isStale(int $version): bool
    {
        $latestVersion = Cache::get($this->key);

        return $latestVersion
            ? $version <= (int) $latestVersion
            : false;
    }

    public function eventProcessed(string $eventId): bool
    {
        return Cache::has($this->buildEventIdKey($eventId));
    }

    public function updateVersion(int $version, string $eventId): void
    {
        Cache::put($this->key, $version);

        Cache::put($this->buildEventIdKey($eventId), true);
    }

    private function buildEventIdKey(string $eventId): string
    {
        return $this->key.self::EVENT_ID_SUFFIX.'_'.$eventId;
    }
}
