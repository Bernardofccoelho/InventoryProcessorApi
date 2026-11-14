<?php

declare(strict_types=1);

namespace App\Features\AvailabilityImport\Show;

use App\Features\AvailabilityImport\Service;
use Illuminate\Http\JsonResponse;

final class Controller
{
    public function __invoke(string $jobId): JsonResponse
    {
        try {
            $job = Service::getJob($jobId);
        } catch (\Exception) {
            return response()->json(['message' => 'Import job not found'], 404);
        }

        return response()->json($job);
    }
}
