<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_product_to_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/cart/add', [
            'user_id' => $user->id,
            'product_id' => $product->id,
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
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_can_view_cart_items(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $products = Product::factory()->count(2)->create();

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $products[0]->id,
            'quantity' => 1,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $products[1]->id,
            'quantity' => 3,
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
