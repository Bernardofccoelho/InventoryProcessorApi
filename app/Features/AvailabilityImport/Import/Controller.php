<?php

declare(strict_types=1);

namespace App\Features\AvailabilityImport\Import;

use Illuminate\Http\JsonResponse;

final class Controller
{
    public const string CACHE_STORE = 'file';

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validated();

        $service = new Service($validated['file']);

        $service->storeFileBatches();

        $service->cacheJobStatus();

        $service->dispatchJobs();

        $jobId = $service->getJobId();

        return response()->json([
            'jobId' => $jobId,
            'status' => 'queued',
            'statusUrl' => route('availability-imports.show', ['jobId' => $jobId]),
        ], 202);
    }
}
