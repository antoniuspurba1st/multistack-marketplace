<?php

namespace App\Services;

use App\Exceptions\ExternalServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SellerService
{
    private const FAILURE_COUNT_CACHE_KEY = 'seller_service:failure_count';

    private const CIRCUIT_OPEN_UNTIL_CACHE_KEY = 'seller_service:circuit_open_until';

    public function getProduct(int $productId): ?array
    {
        if ($cachedProduct = $this->cachedProduct($productId, true)) {
            return $cachedProduct;
        }

        try {
            $response = $this->request('GET', "/products/{$productId}", [], [
                'operation' => 'get_product',
                'product_id' => $productId,
            ]);
        } catch (ExternalServiceException $exception) {
            if ($cachedProduct = $this->cachedProduct($productId)) {
                Log::warning('seller_service_cached_product_fallback', [
                    'product_id' => $productId,
                    'status' => $exception->status(),
                ]);

                return $cachedProduct;
            }

            throw $exception;
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new ExternalServiceException('Seller service unavailable', 503);
        }

        $product = $this->normalizeProduct($response->json());

        Cache::put(
            $this->productCacheKey($productId),
            $product,
            now()->addSeconds($this->productCacheTtlSeconds())
        );

        return $product;
    }

    public function getProducts(array $productIds): array
    {
        return collect($productIds)
            ->filter()
            ->unique()
            ->mapWithKeys(function ($productId) {
                return [(int) $productId => $this->getProduct((int) $productId)];
            })
            ->all();
    }

    public function listProducts(?string $search = null): array
    {
        $response = $this->request('GET', '/products', array_filter([
            'search' => $search,
        ]), [
            'operation' => 'list_products',
            'search' => $search,
        ]);

        if (! $response->successful()) {
            throw new ExternalServiceException('Seller service unavailable', 503);
        }

        return collect($response->json())
            ->map(fn (array $product) => $this->normalizeProduct($product))
            ->all();
    }

    public function createProduct(array $payload): array
    {
        $response = $this->request('POST', '/products', $payload, [
            'operation' => 'create_product',
            'payload' => $payload,
        ]);

        if (! $response->successful()) {
            throw new ExternalServiceException('Failed to create product', 500);
        }

        return $this->normalizeProduct($response->json());
    }

    public function updateProduct(int $productId, array $payload): array
    {
        $response = $this->request('PUT', "/products/{$productId}", $payload, [
            'operation' => 'update_product',
            'product_id' => $productId,
            'payload' => $payload,
        ]);

        if (! $response->successful()) {
            throw new ExternalServiceException('Failed to update product', 500);
        }

        return $this->normalizeProduct($response->json());
    }

    public function deleteProduct(int $productId): void
    {
        $response = $this->request('DELETE', "/products/{$productId}", [], [
            'operation' => 'delete_product',
            'product_id' => $productId,
        ]);

        if (! $response->successful()) {
            throw new ExternalServiceException('Failed to delete product', 500);
        }
    }

    public function reserveStock(array $items, string $requestId): array
    {
        $response = $this->request('POST', '/products/reserve', [
            'items' => $items,
            'request_id' => $requestId,
        ], [
            'operation' => 'reserve_stock',
            'request_id' => $requestId,
            'payload' => $items,
        ]);

        if ($response->status() === 404) {
            throw new ExternalServiceException('Product not found', 404);
        }

        if ($response->status() === 422) {
            throw new ExternalServiceException(
                $response->json('message', 'Stock not enough'),
                422
            );
        }

        if (! $response->successful()) {
            throw new ExternalServiceException('Seller service unavailable', 503);
        }

        return [
            'reservation_id' => (string) $response->json('reservation_id'),
            'status' => $response->json('status'),
            'products' => $this->normalizeProducts($response->json('products', [])),
        ];
    }

    public function confirmReservation(string $reservationId): array
    {
        $response = $this->request('POST', '/products/confirm', [
            'reservation_id' => $reservationId,
        ], [
            'operation' => 'confirm_reservation',
            'reservation_id' => $reservationId,
        ]);

        if ($response->status() === 404) {
            throw new ExternalServiceException('Reservation not found', 404);
        }

        if ($response->status() === 422) {
            throw new ExternalServiceException(
                $response->json('message', 'Reservation confirmation failed'),
                422
            );
        }

        if (! $response->successful()) {
            throw new ExternalServiceException('Seller service unavailable', 503);
        }

        return $response->json();
    }

    public function releaseReservation(string $reservationId): array
    {
        $response = $this->request('POST', '/products/release', [
            'reservation_id' => $reservationId,
        ], [
            'operation' => 'release_reservation',
            'reservation_id' => $reservationId,
        ]);

        if ($response->status() === 404) {
            throw new ExternalServiceException('Reservation not found', 404);
        }

        if ($response->status() === 422) {
            throw new ExternalServiceException(
                $response->json('message', 'Reservation release failed'),
                422
            );
        }

        if (! $response->successful()) {
            throw new ExternalServiceException('Seller service unavailable', 503);
        }

        return $response->json();
    }

    private function request(string $method, string $path, array $payload = [], array $context = []): Response
    {
        $this->guardCircuit($context);

        try {
            $pendingRequest = Http::timeout($this->timeoutSeconds())
                ->retry(
                    $this->retryTimes(),
                    fn (int $attempt) => $attempt * $this->retryBackoffMilliseconds(),
                    null,
                    false
                );

            $response = match (strtoupper($method)) {
                'GET' => $pendingRequest->get($this->baseUrl().$path, $payload),
                'POST' => $pendingRequest->post($this->baseUrl().$path, $payload),
                'PUT' => $pendingRequest->put($this->baseUrl().$path, $payload),
                'DELETE' => $pendingRequest->delete($this->baseUrl().$path, $payload),
                default => throw new \InvalidArgumentException("Unsupported method [{$method}]"),
            };
        } catch (ConnectionException $exception) {
            $this->recordFailure($context, null, $exception);

            throw new ExternalServiceException('Seller service temporarily unavailable', 503);
        }

        if ($response->serverError() || $response->status() === 429) {
            $this->recordFailure($context, $response);

            throw new ExternalServiceException('Seller service temporarily unavailable', 503);
        }

        $this->resetCircuit();

        return $response;
    }

    private function guardCircuit(array $context): void
    {
        $openUntil = (int) Cache::get(self::CIRCUIT_OPEN_UNTIL_CACHE_KEY, 0);

        if ($openUntil <= now()->timestamp) {
            return;
        }

        Log::warning('seller_service_circuit_open', $context + [
            'open_until' => $openUntil,
        ]);

        throw new ExternalServiceException('Seller service temporarily unavailable', 503);
    }

    private function recordFailure(array $context, ?Response $response = null, ?\Throwable $exception = null): void
    {
        Cache::add(
            self::FAILURE_COUNT_CACHE_KEY,
            0,
            now()->addSeconds($this->circuitOpenSeconds())
        );

        $failureCount = Cache::increment(self::FAILURE_COUNT_CACHE_KEY);

        if ($failureCount >= $this->circuitFailureThreshold()) {
            $openUntil = now()->addSeconds($this->circuitOpenSeconds())->timestamp;

            Cache::put(
                self::CIRCUIT_OPEN_UNTIL_CACHE_KEY,
                $openUntil,
                now()->addSeconds($this->circuitOpenSeconds())
            );
        }

        Log::error('seller_service_request_failed', $context + [
            'failure_count' => $failureCount,
            'status' => $response?->status(),
            'response_body' => $response?->json(),
            'exception' => $exception?->getMessage(),
        ]);
    }

    private function resetCircuit(): void
    {
        Cache::forget(self::FAILURE_COUNT_CACHE_KEY);
        Cache::forget(self::CIRCUIT_OPEN_UNTIL_CACHE_KEY);
    }

    private function normalizeProduct(array $product): array
    {
        return [
            'id' => (int) ($product['id'] ?? 0),
            'name' => $product['name'] ?? null,
            'price' => (float) ($product['price'] ?? 0),
            'stock' => isset($product['stock']) ? (int) $product['stock'] : null,
        ];
    }

    private function normalizeProducts(array $products): array
    {
        return collect($products)
            ->mapWithKeys(function (array $product) {
                $normalized = $this->normalizeProduct($product);

                return [(int) $normalized['id'] => $normalized];
            })
            ->all();
    }

    private function cachedProduct(int $productId, bool $onlyWhenCircuitOpen = false): ?array
    {
        if ($onlyWhenCircuitOpen && (int) Cache::get(self::CIRCUIT_OPEN_UNTIL_CACHE_KEY, 0) <= now()->timestamp) {
            return null;
        }

        return Cache::get($this->productCacheKey($productId));
    }

    private function productCacheKey(int $productId): string
    {
        return "seller_service:product:{$productId}";
    }

    private function productCacheTtlSeconds(): int
    {
        return (int) config('services.seller.product_cache_ttl_seconds', 300);
    }

    private function timeoutSeconds(): int
    {
        return (int) config('services.seller.timeout', 3);
    }

    private function retryTimes(): int
    {
        return (int) config('services.seller.retry_times', 3);
    }

    private function retryBackoffMilliseconds(): int
    {
        return (int) config('services.seller.retry_backoff_ms', 100);
    }

    private function circuitFailureThreshold(): int
    {
        return (int) config('services.seller.circuit_breaker_threshold', 3);
    }

    private function circuitOpenSeconds(): int
    {
        return (int) config('services.seller.circuit_breaker_seconds', 30);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.seller.url'), '/');
    }
}
