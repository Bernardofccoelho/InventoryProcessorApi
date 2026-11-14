<?php

declare(strict_types=1);

namespace App\Features\InventoryEventProcessing;

use App\Actions\ProcessInventoryEvent;
use App\Http\Traits\MockBrokerApiHelper;
use App\Http\Traits\Product\AvailabilityApiHelper;
use App\Http\Traits\Product\ContextApiHelper;
use App\Services\LockService;

final class Service
{
    use AvailabilityApiHelper, ContextApiHelper, MockBrokerApiHelper;

    private readonly LockService $lockService;

    public function __construct(
        private readonly InventoryEvent $inventoryEvent,
    ) {
        $this->lockService = new LockService;
    }

    public function process(): bool
    {
        $lock = $this->lockService->acquire(
            key: $this->inventoryEvent->uniqueKey(),
        );

        try {
            $result = (new ProcessInventoryEvent($this->inventoryEvent))->handle();
        } finally {
            $this->lockService->release($lock);
        }

        return $result;
    }
}
