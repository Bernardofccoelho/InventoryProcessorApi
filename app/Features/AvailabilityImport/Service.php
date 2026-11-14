<?php

declare(strict_types=1);

namespace App\Features\AvailabilityImport;

use App\Features\AvailabilityImport\Dto\Job;
use Illuminate\Support\Facades\Cache;

abstract class Service
{
    public const string CACHE_STORE = 'file';

    private const string AVAILABILITY_IMPORT_SUFFIX = 'availability-import';

    public function __construct(protected readonly string $jobId) {}

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public static function key(string $jobId): string
    {
        return self::AVAILABILITY_IMPORT_SUFFIX.':'.$jobId;
    }

    public static function updateJob(string $jobId, Job $job): void
    {
        $key = self::key($jobId);

        Cache::store(self::CACHE_STORE)->put($key, $job->toArray(), now()->addDays(7));
    }

    public static function getJob(string $jobId): Job
    {
        $job = Cache::store(Service::CACHE_STORE)->get(self::key($jobId));

        if ($job === null) {
            throw new \RuntimeException('Job not found in cache.');
        }

        return Job::fromArray($job);
    }
}
