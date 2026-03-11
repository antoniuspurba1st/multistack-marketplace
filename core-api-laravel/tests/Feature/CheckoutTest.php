<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Product;
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

        Http::fake([
            '*' => Http::response([
                'recommendations' => [
                    ['product_id' => 999],
                ],
            ], 200),
        ]);
    }

    public function test_checkout_creates_order(): void
    {
        [$user, $cart] = $this->createCartWithItems();

        $response = $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('message', 'Checkout successful')
            ->assertJsonStructure([
                'message',
                'order' => [
                    'id',
                    'user_id',
                    'total_price',
                    'created_at',
                    'updated_at',
                ],
                'recommendations',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('order.id'),
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);
    }

    public function test_checkout_creates_order_items(): void
    {
        [$user, , $firstProduct, $secondProduct] = $this->createCartWithItems();

        $response = $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ]);

        $order = Order::findOrFail($response->json('order.id'));

        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $firstProduct->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $secondProduct->id,
            'quantity' => 1,
        ]);
    }

    public function test_checkout_reduces_product_stock(): void
    {
        [$user, , $firstProduct, $secondProduct] = $this->createCartWithItems();

        $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $firstProduct->id,
            'stock' => 8,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $secondProduct->id,
            'stock' => 4,
        ]);
    }

    public function test_checkout_validates_user_id(): void
    {
        $this->postJson('/api/checkout', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_checkout_is_idempotent(): void
    {
        [$user] = $this->createCartWithItems();

        $headers = ['Idempotency-Key' => 'checkout-key-1'];

        $firstResponse = $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ], $headers)->assertStatus(200);

        $secondResponse = $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ], $headers)->assertStatus(200);

        $this->assertSame(
            $firstResponse->json(),
            $secondResponse->json()
        );

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'checkout-key-1',
            'user_id' => $user->id,
        ]);
    }

    public function test_checkout_rolls_back_when_stock_is_not_enough(): void
    {
        [$user, $cart, $firstProduct] = $this->createCartWithItems();

        CartItem::query()->where('cart_id', $cart->id)->first()->update([
            'quantity' => 99,
        ]);

        $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Stock not enough');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseMissing('outbox_events', [
            'event_type' => 'OrderCreated',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $firstProduct->id,
            'stock' => 10,
        ]);
    }

    public function test_checkout_creates_unprocessed_outbox_event(): void
    {
        [$user] = $this->createCartWithItems();

        $response = $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'OrderCreated',
            'processed_at' => null,
        ]);

        $event = OutboxEvent::first();

        $this->assertSame($response->json('order.id'), $event->payload['order_id']);
        $this->assertSame($user->id, $event->payload['user_id']);
    }

    public function test_checkout_updates_stored_idempotent_response_with_recommendations(): void
    {
        [$user] = $this->createCartWithItems();

        $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ], [
            'Idempotency-Key' => 'checkout-key-2',
        ])->assertStatus(200);

        $record = IdempotencyKey::where('key', 'checkout-key-2')->firstOrFail();

        $this->assertSame('Checkout successful', $record->response['message']);
        $this->assertSame([['product_id' => 999]], $record->response['recommendations']);
    }

    private function createCartWithItems(): array
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $firstProduct = Product::factory()->create([
            'price' => 100,
            'stock' => 10,
        ]);
        $secondProduct = Product::factory()->create([
            'price' => 250,
            'stock' => 5,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $firstProduct->id,
            'quantity' => 2,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $secondProduct->id,
            'quantity' => 1,
        ]);

        return [$user, $cart, $firstProduct, $secondProduct];
    }
}
