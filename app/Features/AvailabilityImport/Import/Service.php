<?php

declare(strict_types=1);

namespace App\Features\AvailabilityImport\Import;

use App\Features\AvailabilityImport\Dto\Job;
use App\Features\AvailabilityImport\Service as AvailabilityImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class Service extends AvailabilityImportService
{
    private const int BATCH_SIZE = 1000;

    private array $filePaths = [];

    private int $totalRows = 0;

    public function __construct(private readonly UploadedFile $file)
    {
        parent::__construct((string) Str::uuid());
    }

    public function storeFileBatches(): void
    {
        $fileExtension = $this->file->getClientOriginalExtension();

        $stream = fopen($this->file->getRealPath(), 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to read uploaded CSV.');
        }

        try {
            $headers = fgetcsv($stream);

            if ($headers === false) {
                throw new RuntimeException('CSV is empty.');
            }

            $batchNumber = 0;

            while (true) {
                $temp = fopen('php://temp', 'w+');
                fputcsv($temp, $headers);

                $rowsInBatch = 0;

                while ($rowsInBatch < self::BATCH_SIZE && ($row = fgetcsv($stream)) !== false) {
                    if ($row === [null] || $row === []) {
                        continue;
                    }

                    fputcsv($temp, $row);
                    $rowsInBatch++;
                    $this->totalRows++;
                }

                if ($rowsInBatch === 0) {
                    fclose($temp);
                    break;
                }

                rewind($temp);
                $path = $this->key($this->jobId).'-'.(++$batchNumber).'.'.$fileExtension;
                Storage::disk('local')->put($path, stream_get_contents($temp));
                fclose($temp);

                $this->filePaths[] = $path;
            }
        } finally {
            fclose($stream);
        }
    }

    public function cacheJobStatus(): void
    {
        $job = new Job(
            jobId: $this->jobId,
            status: 'queued',
            totalRows: $this->totalRows,
            processedRows: 0,
            successfulRows: 0,
            failedRows: 0,
            skippedRows: 0,
            errors: [],
            createdAt: now()->toIso8601String(),
            updatedAt: now()->toIso8601String(),
        );

        $this->updateJob($this->jobId, $job);
    }

    public function dispatchJobs(): void
    {
        $jobs = array_map(
            fn (string $path): ProcessAvailabilityImport => new ProcessAvailabilityImport($this->jobId, $path, $this->totalRows),
            $this->filePaths,
        );

        if ($jobs === []) {
            $job = AvailabilityImportService::getJob($this->jobId);
            $job->status = 'completed';
            AvailabilityImportService::updateJob($this->jobId, $job);

            return;
        }

        Bus::chain($jobs)
            ->onConnection('background')
            ->dispatch();
    }
}
