<?php

declare(strict_types=1);

namespace App\Http\Traits\Product;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

trait ContextApiHelper
{
    private int $numAttempts = 0;

    private const int MAX_ATTEMPTS = 3;

    public function getProductContext(
        string $productId,
        string $sizeId,
        string $countryCode
    ): Response {
        $baseUrl = config('services.contextApi.baseUrl');

        $url = sprintf(
            '%s/v1/products/%s/sizes/%s',
            $baseUrl,
            $productId,
            $sizeId,
        );

        $response = Http::get($url, [
            'countryCode' => $countryCode,
        ]);

        Log::info('Product context API request', [
            'url' => $url,
            'request' => [
                'countryCode' => $countryCode,
            ],
            'response' => [
                'status' => $response->status(),
                'body' => $response->body(),
            ],
        ]);

        if ($response->successful()) {
            $this->validateResponseContent(
                response: $response,
                productId: $productId,
                sizeId: $sizeId,
                countryCode: $countryCode,
            );

            return $response;
        }

        if ($response->status() === 429) {
            $this->numAttempts++;

            if ($this->numAttempts <= self::MAX_ATTEMPTS) {
                $retryAfter = (int) ($response->header('Retry-After') ?? 1);
                sleep($retryAfter);

                return $this->getProductContext($productId, $sizeId, $countryCode);
            }

            throw new RuntimeException('Max attempts reached for product context API');
        }

        if ($response->notFound()) {
            Log::error('Product context not found', [
                'productId' => $productId,
                'sizeId' => $sizeId,
                'countryCode' => $countryCode,
            ]);
        } elseif ($response->status() >= 500) {
            Log::error('Product context API server error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } else {
            Log::error('Product context API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        throw new RuntimeException;
    }

    private function validateResponseContent(Response $response, string $productId, string $sizeId, string $countryCode): void
    {
        $data = $response->json();

        if (! isset(
            $data['productId'],
            $data['productName'],
            $data['brand']['id'],
            $data['brand']['name'],
            $data['category']['id'],
            $data['category']['name'],
            $data['color']['id'],
            $data['color']['name'],
            $data['productStatus'],
            $data['sizeId'],
            $data['sizeLabel'],
            $data['sizeSystem'],
            $data['countryCode'],
            $data['countryEnabled'],
        )) {
            throw new RuntimeException('Invalid response structure from product context API');
        }

        if ($data['productId'] !== $productId ||
            $data['sizeId'] !== $sizeId ||
            $data['countryCode'] !== $countryCode) {
            throw new RuntimeException('Response data does not match request parameters');
        }

        if ($data['countryEnabled'] !== true) {
            throw new RuntimeException('Product is not enabled for the specified country');
        }

        if (strtolower($data['productStatus']) !== 'active') {
            throw new RuntimeException('Product is not active');
        }
    }
}
