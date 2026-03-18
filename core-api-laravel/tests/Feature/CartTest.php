<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.seller.url', 'http://seller.test');
    }

    public function test_can_add_product_to_cart(): void
    {
        $user = User::factory()->create();

        Http::fake([
            config('services.seller.url').'/products/1' => Http::response([
                'id' => 1,
                'name' => 'Test Product',
                'price' => 100,
                'stock' => 10,
            ], 200),
        ]);

        $response = $this->postJson('/api/cart/add', [
            'user_id' => $user->id,
            'product_id' => 1,
            'quantity' => 2,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'cart_id',
                    'product_id',
                    'quantity',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonFragment([
                'product_id' => 1,
                'quantity' => 2,
            ]);

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => 1,
            'quantity' => 2,
        ]);
    }

    public function test_can_view_cart_items(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 1,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => 2,
            'quantity' => 3,
        ]);

        Http::fake([
            config('services.seller.url').'/products/1' => Http::response([
                'id' => 1,
                'name' => 'Test Product',
                'price' => 100,
                'stock' => 10,
            ], 200),
            config('services.seller.url').'/products/2' => Http::response([
                'id' => 2,
                'name' => 'Second Product',
                'price' => 250,
                'stock' => 5,
            ], 200),
        ]);

        $response = $this->getJson("/api/cart/{$user->id}");

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'user_id',
                    'items' => [
                        '*' => [
                            'id',
                            'cart_id',
                            'product_id',
                            'quantity',
                            'product' => [
                                'id',
                                'name',
                                'price',
                                'stock',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data.items'));
        $this->assertSame('Test Product', $response->json('data.items.0.product.name'));
        $this->assertSame('Second Product', $response->json('data.items.1.product.name'));
    }

    public function test_can_remove_cart_item(): void
    {
        $cartItem = CartItem::factory()->create();

        $response = $this->deleteJson("/api/cart/item/{$cartItem->id}");

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Item removed',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }
}
