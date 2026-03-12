<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'user_id',
                            'name',
                            'description',
                            'price',
                            'stock',
                            'created_at',
                            'updated_at',
                            'images',
                        ],
                    ],
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_can_create_product(): void
    {
        $user = User::factory()->create();

        $payload = [
            'user_id' => $user->id,
            'name' => 'Gaming Laptop',
            'description' => 'High-end laptop for development and gaming.',
            'price' => 1499.99,
            'stock' => 12,
        ];

        $response = $this->postJson('/api/products', $payload);

        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'user_id',
                    'name',
                    'description',
                    'price',
                    'stock',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonFragment([
                'user_id' => $user->id,
                'name' => 'Gaming Laptop',
                'stock' => 12,
            ]);

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'Gaming Laptop',
            'stock' => 12,
        ]);
    }

    public function test_product_pagination_works(): void
    {
        Product::factory()->count(15)->create();

        $response = $this->getJson('/api/products?page=2');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.total', 15);

        $this->assertCount(5, $response->json('data.data'));
    }

    public function test_product_search_works(): void
    {
        Product::factory()->create(['name' => 'Apple iPhone 16']);
        Product::factory()->create(['name' => 'Samsung Galaxy Ultra']);
        Product::factory()->create(['name' => 'Apple Watch Pro']);

        $response = $this->getJson('/api/products?search=Apple');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.total', 2);

        $names = collect($response->json('data.data'))->pluck('name');

        $this->assertTrue($names->contains('Apple iPhone 16'));
        $this->assertTrue($names->contains('Apple Watch Pro'));
        $this->assertFalse($names->contains('Samsung Galaxy Ultra'));
    }
}
