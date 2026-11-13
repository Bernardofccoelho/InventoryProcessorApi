<?php

declare(strict_types=1);

namespace App\Http\Traits\Product;

use App\Dto\Product\Context\ProductContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

trait AvailabilityApiHelper
{
    private int $numAttempts = 0;

    private const int MAX_ATTEMPTS = 3;

    public function putAvailability(
        string $sellerId,
        string $productId,
        string $sizeId,
        string $countryCode,
        int $availableQuantity,
        int $sourceVersion,
        ProductContext $productContext,
    ): void {
        $baseUrl = config('services.availabilityApi.baseUrl');

        $url = sprintf(
            '%s/v1/availability/%s/%s/%s',
            $baseUrl,
            $sellerId,
            $productId,
            $sizeId
        );

        $url .= '?'.http_build_query([
            'countryCode' => $countryCode,
        ]);

        $response = Http::put($url, [
            'availableQuantity' => $availableQuantity,
            'sourceVersion' => $sourceVersion,
            'productContext' => $productContext->toAvailabilityRequestArray(),
        ]);

        Log::info('Availability API request', [
            'url' => $url,
            'request' => [
                'availableQuantity' => $availableQuantity,
                'sourceVersion' => $sourceVersion,
                'productContext' => $productContext->toAvailabilityRequestArray(),
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

                $this->putAvailability(
                    sellerId: $sellerId,
                    productId: $productId,
                    sizeId: $sizeId,
                    countryCode: $countryCode,
                    availableQuantity: $availableQuantity,
                    sourceVersion: $sourceVersion,
                    productContext: $productContext
                );
            }

            throw new RuntimeException('Max attempts reached for availability API');
        }

        Log::error('Availability API request failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new RuntimeException;
    }
}
