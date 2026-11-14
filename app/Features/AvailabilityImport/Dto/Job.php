<?php

declare(strict_types=1);

namespace App\Features\AvailabilityImport\Dto;

final class Job
{
    public function __construct(
        public readonly string $jobId,
        public string $status,
        public readonly int $totalRows,
        public int $processedRows,
        public int $successfulRows,
        public int $failedRows,
        public int $skippedRows,
        public array $errors,
        public readonly string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            jobId: $data['jobId'],
            status: $data['status'],
            totalRows: $data['totalRows'],
            processedRows: $data['processedRows'],
            successfulRows: $data['successfulRows'],
            failedRows: $data['failedRows'],
            skippedRows: $data['skippedRows'],
            errors: $data['errors'],
            createdAt: $data['createdAt'],
            updatedAt: $data['updatedAt'],
        );
    }

    public function toArray(): array
    {
        return [
            'jobId' => $this->jobId,
            'status' => $this->status,
            'totalRows' => $this->totalRows,
            'processedRows' => $this->processedRows,
            'successfulRows' => $this->successfulRows,
            'failedRows' => $this->failedRows,
            'skippedRows' => $this->skippedRows,
            'errors' => $this->errors,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
