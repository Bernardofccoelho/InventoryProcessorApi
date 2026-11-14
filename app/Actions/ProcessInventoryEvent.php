<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\Product\Context\ProductContext;
use App\Features\InventoryEventProcessing\InventoryEvent;
use App\Http\Traits\MockBrokerApiHelper;
use App\Http\Traits\Product\AvailabilityApiHelper;
use App\Http\Traits\Product\ContextApiHelper;
use App\Services\IdempotencyService;

final class ProcessInventoryEvent
{
    use AvailabilityApiHelper, ContextApiHelper, MockBrokerApiHelper;

    private const string SELLER_INVENTORY_CHANGED_EVENT_TYPE = 'SellerInventoryChanged';

    private readonly IdempotencyService $idempotencyService;

    private ProductContext $productContext;

    public function __construct(private readonly InventoryEvent $inventoryEvent)
    {
        $this->idempotencyService = new IdempotencyService(
            key: $inventoryEvent->uniqueKey(),
        );
    }

    public function handle(): bool
    {
        if (! $this->eventIsValid()) {
            return false;
        }

        $this->fetchProductContext();
        $this->updateProductAvailability();
        $this->outputEvent();
        $this->updateProductSouceVersion();

        return true;
    }

    private function fetchProductContext(): void
    {
        $productContextResponse = $this->getProductContext(
            productId: $this->inventoryEvent->productId,
            sizeId: $this->inventoryEvent->sizeId,
            countryCode: $this->inventoryEvent->countryCode
        );

        $this->productContext = ProductContext::fromArray($productContextResponse->json());
    }

    private function updateProductAvailability(): void
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

    private function outputEvent(): void
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

    private function eventIsValid(): bool
    {
        return ! $this->idempotencyService->isStale($this->inventoryEvent->sourceVersion)
            && ! $this->idempotencyService->eventProcessed($this->inventoryEvent->eventId);
    }

    private function updateProductSouceVersion(): void
    {
        $this->idempotencyService->updateVersion($this->inventoryEvent->sourceVersion, $this->inventoryEvent->eventId);
    }
}
