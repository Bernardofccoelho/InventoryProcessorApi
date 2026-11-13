<?php

declare(strict_types=1);

namespace App\Features\InventoryEventProcessing;

final readonly class InventoryEvent
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $schemaVersion,
        public string $occurredAt,
        public string $sellerId,
        public string $productId,
        public string $sizeId,
        public string $countryCode,
        public int $availableQuantity,
        public int $sourceVersion
    ) {}

    public function uniqueKey(): string
    {
        return sprintf(
            '%s:%s:%s:%s',
            $this->sellerId,
            $this->productId,
            $this->sizeId,
            $this->countryCode
        );
    }
}
