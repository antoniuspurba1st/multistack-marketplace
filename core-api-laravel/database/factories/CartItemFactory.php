<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => fake()->numberBetween(1, 1000),
            'quantity' => fake()->numberBetween(1, 5),
        ];
    }
}
