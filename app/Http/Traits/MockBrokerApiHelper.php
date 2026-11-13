<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Dto\Product\Context\ProductContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

trait MockBrokerApiHelper
{
    private int $numAttempts = 0;

    private const int MAX_ATTEMPTS = 3;

    public function postMockBrokerEvent(
        string $eventId,
        string $eventType,
        int $schemaVersion,
        string $sellerId,
        string $productId,
        string $sizeId,
        string $countryCode,
        int $availableQuantity,
        int $sourceVersion,
        ProductContext $productContext,
    ): void {
        $baseUrl = config('services.mockBrokerApi.baseUrl');

        $url = sprintf(
            '%s/mock-broker/v1/events',
            $baseUrl
        );

        $response = Http::post($url, [
            'eventId' => $eventId,
            'eventType' => $eventType,
            'schemaVersion' => $schemaVersion,
            'occurredAt' => now()->toIso8601String(),
            'availableQuantity' => $availableQuantity,
            'sellerId' => $sellerId,
            'productId' => $productId,
            'sizeId' => $sizeId,
            'sourceVersion' => $sourceVersion,
            'productContext' => $productContext->toMockBrokerEventRequestArray(),
        ]);

        Log::info('Mock broker API request', [
            'url' => $url,
            'request' => [
                'eventId' => $eventId,
                'eventType' => $eventType,
                'schemaVersion' => $schemaVersion,
                'occurredAt' => now()->toIso8601String(),
                'availableQuantity' => $availableQuantity,
                'sellerId' => $sellerId,
                'productId' => $productId,
                'sizeId' => $sizeId,
                'sourceVersion' => $sourceVersion,
                'productContext' => $productContext->toMockBrokerEventRequestArray(),
            ],
            'response' => [
                'status' => $response->status(),
                'body' => $response->body(),
            ],
        ]);

        if ($response->successful()) {
            return;
        }

        if ($response->status() === 429) {
            $this->numAttempts++;

            if ($this->numAttempts <= self::MAX_ATTEMPTS) {
                $retryAfter = (int) ($response->header('Retry-After') ?? 1);
                sleep($retryAfter);

                $this->postMockBrokerEvent(
                    eventId: $eventId,
                    eventType: $eventType,
                    schemaVersion: $schemaVersion,
                    sellerId: $sellerId,
                    productId: $productId,
                    sizeId: $sizeId,
                    countryCode: $countryCode,
                    availableQuantity: $availableQuantity,
                    sourceVersion: $sourceVersion,
                    productContext: $productContext
                );
            }

            throw new RuntimeException('Max attempts reached for mock broker API');
        }

        Log::error('Mock broker API request failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new RuntimeException;
    }
}
