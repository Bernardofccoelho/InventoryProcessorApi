<?php

declare(strict_types=1);

namespace App\Features\AvailabilityImport\Import;

use App\Actions\ProcessInventoryEvent;
use App\Features\InventoryEventProcessing\InventoryEvent;
use App\Services\LockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProcessAvailabilityImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private readonly LockService $lockService;

    public function __construct(
        private readonly string $jobId,
        private readonly string $path,
        private readonly int $totalRows,
    ) {
        $this->lockService = new LockService;
    }

    public function handle(): void
    {
        $job = Service::getJob($this->jobId);

        $job->status = 'processing';
        $job->updatedAt = now()->toIso8601String();

        Service::updateJob($this->jobId, $job);

        $stream = Storage::disk('local')->readStream($this->path);
        if (! is_resource($stream)) {
            throw new \RuntimeException('Unable to read uploaded CSV.');
        }

        try {
            $headers = fgetcsv($stream);
            if (! is_array($headers)) {
                throw new \RuntimeException('CSV is empty.');
            }
            $headers = array_map(static fn ($header): string => trim((string) $header), $headers);
            $indexes = $this->columnIndexes($headers);
            $line = 1;
            while (($row = fgetcsv($stream)) !== false) {
                $line++;
                if ($row === [null] || $row === []) {
                    continue;
                }

                try {
                    $values = [];
                    foreach ($indexes as $field => $index) {
                        $values[$field] = trim((string) ($row[$index] ?? ''));
                    }
                    $this->processRow($values, $line);
                } catch (Throwable $exception) {
                    $job = Service::getJob($this->jobId);
                    $job->failedRows++;
                    if (count($job->errors) < 100) {
                        $job->errors[] = ['row' => $line, 'message' => $exception->getMessage()];
                    }
                    Service::updateJob($this->jobId, $job);
                } finally {
                    $job = Service::getJob($this->jobId);
                    $job->processedRows++;
                    $job->updatedAt = now()->toIso8601String();

                    if ($job->processedRows >= $this->totalRows) {
                        $job->status = $job->failedRows > 0
                            ? 'completed_with_errors'
                            : ($job->skippedRows > 0 ? 'completed_with_skips' : 'completed');
                    }

                    Service::updateJob($this->jobId, $job);
                }
            }
        } finally {
            fclose($stream);
        }

        Storage::disk('local')->delete($this->path);
    }

    public function failed(Throwable $exception): void
    {
        $job = Service::getJob($this->jobId);
        if ($job !== null) {
            $job->status = 'failed';
            $job->errors[] = ['message' => $exception->getMessage()];
            $job->updatedAt = now()->toIso8601String();

            Service::updateJob($this->jobId, $job);
        }
    }

    private function columnIndexes(array $headers): array
    {
        $aliases = [
            'sellerId' => ['sellerId', 'seller_id'],
            'productId' => ['productId', 'product_id'],
            'sizeId' => ['sizeId', 'size_id'],
            'countryCode' => ['countryCode', 'country_code'],
            'availableQuantity' => ['availableQuantity', 'available_quantity'],
            'sourceVersion' => ['sourceVersion', 'source_version'],
        ];
        $indexes = [];

        foreach ($aliases as $field => $names) {
            $index = null;
            foreach ($names as $name) {
                $found = array_search($name, $headers, true);
                if ($found !== false) {
                    $index = $found;
                    break;
                }
            }
            if ($index === null) {
                throw new \RuntimeException('File is missing required column: '.$field);
            }
            $indexes[$field] = $index;
        }

        return $indexes;
    }

    private function processRow(array $row, int $line): void
    {
        if ($row['sellerId'] === '' || $row['productId'] === '' || $row['sizeId'] === '' || strlen($row['countryCode']) !== 2
            || filter_var($row['availableQuantity'], FILTER_VALIDATE_INT) === false
            || filter_var($row['sourceVersion'], FILTER_VALIDATE_INT) === false) {
            throw new \RuntimeException('Row has missing or invalid field values.');
        }

        $event = new InventoryEvent(
            eventId: 'bulk-'.$this->jobId.'-'.$line,
            eventType: 'SellerInventoryChanged',
            schemaVersion: 1,
            occurredAt: now()->toIso8601String(),
            sellerId: $row['sellerId'],
            productId: $row['productId'],
            sizeId: $row['sizeId'],
            countryCode: strtoupper($row['countryCode']),
            availableQuantity: (int) $row['availableQuantity'],
            sourceVersion: (int) $row['sourceVersion'],
        );

        $lock = $this->lockService->acquire(
            key: $event->uniqueKey(),
        );

        try {
            $result = (new ProcessInventoryEvent($event))->handle();

            $job = Service::getJob($this->jobId);

            $job->updatedAt = now()->toIso8601String();

            if ($result) {
                $job->successfulRows++;
            } else {
                $job->skippedRows++;
            }

            Service::updateJob($this->jobId, $job);
        } finally {
            $this->lockService->release($lock);
        }
    }
}
