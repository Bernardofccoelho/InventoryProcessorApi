<?php

declare(strict_types=1);

namespace App\Features\InventoryEventProcessing;

use App\Dto\Product\Context\ProductContext;
use App\Http\Traits\MockBrokerApiHelper;
use App\Http\Traits\Product\AvailabilityApiHelper;
use App\Http\Traits\Product\ContextApiHelper;
use App\Services\IdempotencyService;
use App\Services\LockService;
use Illuminate\Contracts\Cache\Lock;

final class Service
{
    use AvailabilityApiHelper, ContextApiHelper, MockBrokerApiHelper;

    private const string SELLER_INVENTORY_CHANGED_EVENT_TYPE = 'SellerInventoryChanged';

    private readonly IdempotencyService $idempotencyService;

    private readonly LockService $lockService;

    private ProductContext $productContext;

    private Lock $lock;

    public function __construct(
        private readonly InventoryEvent $inventoryEvent,
    ) {
        $this->idempotencyService = new IdempotencyService(
            key: $inventoryEvent->uniqueKey(),
        );

        $this->lockService = new LockService;
    }

    public function eventIsValid(): bool
    {
        return ! $this->idempotencyService->isStale($this->inventoryEvent->sourceVersion)
            && ! $this->idempotencyService->eventProcessed($this->inventoryEvent->eventId);
    }

    public function acquireLock(): void
    {
        $this->lock = $this->lockService->acquire(
            key: $this->inventoryEvent->uniqueKey(),
        );
    }

    public function fetchProductContext(): void
    {
        $productContextResponse = $this->getProductContext(
            productId: $this->inventoryEvent->productId,
            sizeId: $this->inventoryEvent->sizeId,
            countryCode: $this->inventoryEvent->countryCode
        );

        $this->productContext = ProductContext::fromArray($productContextResponse->json());
    }

    public function updateProductAvailability(): void
    {
        $this->putAvailability(
            sellerId: $this->inventoryEvent->sellerId,
            productId: $this->inventoryEvent->productId,
            sizeId: $this->inventoryEvent->sizeId,
            countryCode: $this->inventoryEvent->countryCode,
            availableQuantity: $this->inventoryEvent->availableQuantity,
            sourceVersion: $this->inventoryEvent->sourceVersion,
            productContext: $this->productContext
        );
    }

    public function outputEvent(): void
    {
        $this->postMockBrokerEvent(
            eventId: $this->inventoryEvent->eventId,
            eventType: self::SELLER_INVENTORY_CHANGED_EVENT_TYPE,
            schemaVersion: $this->inventoryEvent->schemaVersion,
            sellerId: $this->inventoryEvent->sellerId,
            productId: $this->inventoryEvent->productId,
            sizeId: $this->inventoryEvent->sizeId,
            countryCode: $this->inventoryEvent->countryCode,
            availableQuantity: $this->inventoryEvent->availableQuantity,
            sourceVersion: $this->inventoryEvent->sourceVersion,
            productContext: $this->productContext
        );
    }

    public function updateProductSouceVersion(): void
    {
        $this->idempotencyService->updateVersion($this->inventoryEvent->sourceVersion, $this->inventoryEvent->eventId);
    }

    public function releaseLock(): void
    {
        if (isset($this->lock)) {
            $this->lockService->release($this->lock);
        }
    }
}
