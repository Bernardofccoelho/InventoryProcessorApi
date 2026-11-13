<?php

declare(strict_types=1);

namespace App\Features\InventoryEventProcessing;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validatedData = $request->validated();

        $inventoryEvent = new InventoryEvent(
            eventId: $validatedData['eventId'],
            eventType: $validatedData['eventType'],
            schemaVersion: $validatedData['schemaVersion'],
            occurredAt: $validatedData['occurredAt'],
            sellerId: $validatedData['sellerId'],
            productId: $validatedData['productId'],
            sizeId: $validatedData['sizeId'],
            countryCode: $validatedData['countryCode'],
            availableQuantity: $validatedData['availableQuantity'],
            sourceVersion: $validatedData['sourceVersion']
        );

        $service = new Service($inventoryEvent);

        try {
            $service->acquireLock();

            if (! $service->eventIsValid()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Request ignored',
                ], 409);
            }

            $service->fetchProductContext();

            $service->updateProductAvailability();

            $service->outputEvent();

            $service->updateProductSouceVersion();
        } catch (LockTimeoutException) {
            Log::error('Unable to aquire lock', [
                'eventId' => $inventoryEvent->eventId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Service unavailable, please try again',
            ], 423);
        } catch (\Exception $e) {
            Log::error('Error processing inventory event', [
                'exception' => $e,
                'eventId' => $inventoryEvent->eventId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing inventory event',
            ], 500);
        } finally {
            $service->releaseLock();
        }

        return response()->json([
            'success' => true,
            'message' => 'Inventory event processed successfully',
        ]);
    }
}
