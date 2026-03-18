<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set('services.seller.url', 'http://seller.test');
        config()->set('services.recommendation.url', 'http://recommendation.test');
        config()->set('services.seller.timeout', 3);
        config()->set('services.seller.retry_times', 3);
        config()->set('services.seller.retry_backoff_ms', 1);
        config()->set('services.seller.circuit_breaker_threshold', 3);
        config()->set('services.seller.circuit_breaker_seconds', 30);
    }

    public function test_checkout_reserves_stock_confirms_reservation_and_clears_cart(): void
    {
        [$user, $cart] = $this->createCartWithItems();

        $this->fakeSuccessfulCheckout();

        $response = $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('message', 'Checkout successful')
            ->assertJsonPath('data.order.total_price', 450)
            ->assertJsonPath('data.recommendations.0.product_id', 999)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'order' => [
                        'id',
                        'user_id',
                        'total_price',
                        'items' => [
                            '*' => [
                                'product_id',
                                'quantity',
                                'price',
                                'product' => [
                                    'id',
                                    'name',
                                    'price',
                                    'stock',
                                ],
                            ],
                        ],
                    ],
                    'recommendations',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.order.id'),
            'user_id' => $user->id,
            'total_price' => 450,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $response->json('data.order.id'),
            'product_id' => 1,
            'quantity' => 2,
            'price' => 100,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $response->json('data.order.id'),
            'product_id' => 2,
            'quantity' => 1,
            'price' => 250,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'OrderCreated',
            'processed_at' => null,
        ]);

        $this->assertDatabaseMissing('outbox_events', [
            'event_type' => 'SellerReservationConfirmRequested',
        ]);

        Http::assertSent(fn ($request) => $request->url() === config('services.seller.url').'/products/reserve');
        Http::assertSent(fn ($request) => $request->url() === config('services.seller.url').'/products/confirm');
    }

    public function test_checkout_validates_user_id(): void
    {
        $this->postJson('/api/checkout', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_checkout_releases_reservation_when_order_creation_fails(): void
    {
        [$user, $cart] = $this->createCartWithItems();

        Http::fake([
            config('services.seller.url').'/products/reserve' => Http::response([
                'reservation_id' => 'res-release',
                'status' => 'reserved',
                'products' => $this->successfulProductsPayload(),
            ], 200),
            config('services.seller.url').'/products/release' => Http::response([
                'reservation_id' => 'res-release',
                'status' => 'released',
            ], 200),
        ]);

        Order::creating(function () {
            throw new \RuntimeException('Simulated database failure');
        });

        try {
            $this->postJson('/api/checkout', [
                'user_id' => $user->id,
            ])->assertStatus(500)
                ->assertJsonPath('message', 'Checkout failed');

            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_items', 0);
            $this->assertDatabaseHas('cart_items', [
                'cart_id' => $cart->id,
            ]);

            Http::assertSent(fn ($request) => $request->url() === config('services.seller.url').'/products/release');
            Http::assertNotSent(fn ($request) => $request->url() === config('services.seller.url').'/products/confirm');
        } finally {
            Order::flushEventListeners();
        }
    }

    public function test_checkout_queues_release_compensation_when_release_call_fails(): void
    {
        [$user] = $this->createCartWithItems();

        Http::fake([
            config('services.seller.url').'/products/reserve' => Http::response([
                'reservation_id' => 'res-compensate',
                'status' => 'reserved',
                'products' => $this->successfulProductsPayload(),
            ], 200),
            config('services.seller.url').'/products/release' => Http::response([
                'message' => 'seller unavailable',
            ], 503),
        ]);

        Order::creating(function () {
            throw new \RuntimeException('Simulated database failure');
        });

        try {
            $this->postJson('/api/checkout', [
                'user_id' => $user->id,
            ])->assertStatus(500);

            $this->assertDatabaseHas('outbox_events', [
                'event_type' => 'SellerReservationReleaseRequested',
                'processed_at' => null,
            ]);
        } finally {
            Order::flushEventListeners();
        }
    }

    public function test_checkout_is_idempotent_and_does_not_reserve_stock_twice(): void
    {
        [$user] = $this->createCartWithItems();

        $this->fakeSuccessfulCheckout();

        $headers = ['Idempotency-Key' => 'checkout-key-1'];

        $firstResponse = $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ], $headers)->assertStatus(200);

        $secondResponse = $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ], $headers)->assertStatus(200);

        $this->assertSame($firstResponse->json(), $secondResponse->json());
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'checkout-key-1',
            'user_id' => $user->id,
        ]);

        Http::assertSentCount(3);
    }

    public function test_checkout_retries_reservation_request_on_connection_failures(): void
    {
        [$user] = $this->createCartWithItems();

        Http::fake([
            config('services.seller.url').'/products/reserve' => Http::sequence()
                ->pushFailedConnection('Temporary network issue')
                ->pushFailedConnection('Temporary network issue')
                ->push([
                    'reservation_id' => 'res-retry',
                    'status' => 'reserved',
                    'products' => $this->successfulProductsPayload(),
                ], 200),
            config('services.seller.url').'/products/confirm' => Http::response([
                'reservation_id' => 'res-retry',
                'status' => 'confirmed',
            ], 200),
            config('services.recommendation.url').'/api/recommendations/*' => Http::response([
                'recommendations' => [
                    ['product_id' => 999],
                ],
            ], 200),
        ]);

        $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ])->assertStatus(200);

        Http::assertSentCount(5);
    }

    public function test_checkout_queues_confirm_retry_when_confirm_call_fails(): void
    {
        [$user] = $this->createCartWithItems();

        Http::fake([
            config('services.seller.url').'/products/reserve' => Http::response([
                'reservation_id' => 'res-confirm-later',
                'status' => 'reserved',
                'products' => $this->successfulProductsPayload(),
            ], 200),
            config('services.seller.url').'/products/confirm' => Http::response([
                'message' => 'seller unavailable',
            ], 503),
            config('services.recommendation.url').'/api/recommendations/*' => Http::response([
                'recommendations' => [
                    ['product_id' => 999],
                ],
            ], 200),
        ]);

        $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'SellerReservationConfirmRequested',
            'processed_at' => null,
        ]);
    }

    public function test_checkout_updates_stored_idempotent_response_with_recommendations(): void
    {
        [$user] = $this->createCartWithItems();

        $this->fakeSuccessfulCheckout();

        $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ], [
            'Idempotency-Key' => 'checkout-key-2',
        ])->assertStatus(200);

        $record = IdempotencyKey::where('key', 'checkout-key-2')->firstOrFail();

        $this->assertSame('Checkout successful', $record->response['message']);
        $this->assertSame([['product_id' => 999]], $record->response['data']['recommendations']);
    }

    private function createCartWithItems(): array
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => 2,
            'quantity' => 1,
        ]);

        return [$user, $cart];
    }

    private function fakeSuccessfulCheckout(): void
    {
        Http::fake([
            config('services.seller.url').'/products/reserve' => Http::response([
                'reservation_id' => 'res-123',
                'status' => 'reserved',
                'products' => $this->successfulProductsPayload(),
            ], 200),
            config('services.seller.url').'/products/confirm' => Http::response([
                'reservation_id' => 'res-123',
                'status' => 'confirmed',
            ], 200),
            config('services.recommendation.url').'/api/recommendations/*' => Http::response([
                'recommendations' => [
                    ['product_id' => 999],
                ],
            ], 200),
        ]);
    }

    private function successfulProductsPayload(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Test Product',
                'price' => 100,
                'stock' => 8,
            ],
            [
                'id' => 2,
                'name' => 'Second Product',
                'price' => 250,
                'stock' => 4,
            ],
        ];
    }
}
