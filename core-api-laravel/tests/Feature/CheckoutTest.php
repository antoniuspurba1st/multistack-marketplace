<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_order(): void
    {
        [$user, $cart] = $this->create_cart_with_items();

        $response = $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'user_id',
                'total_price',
                'created_at',
                'updated_at',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('id'),
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);
    }

    public function test_checkout_creates_order_items(): void
    {
        [$user, , $firstProduct, $secondProduct] = $this->create_cart_with_items();

        $response = $this->postJson('/api/checkout', [
            'user_id' => $user->id,
        ]);

        $order = Order::findOrFail($response->json('id'));

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
        [$user, , $firstProduct, $secondProduct] = $this->create_cart_with_items();

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

    private function create_cart_with_items(): array
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
