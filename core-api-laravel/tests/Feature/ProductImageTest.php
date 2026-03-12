<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_product_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $response = $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/product/upload', [
                'product_id' => $product->id,
                'image' => UploadedFile::fake()->create('product.jpg', 100, 'image/jpeg'),
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'product_id',
                    'image_path',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.product_id', $product->id);
    }

    public function test_image_is_stored_correctly(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->create('camera.jpg', 100, 'image/jpeg');

        $response = $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/product/upload', [
                'product_id' => $product->id,
                'image' => $file,
            ]);

        $response->assertStatus(200);

        Storage::disk('public')->assertExists($response->json('data.image_path'));
    }

    public function test_database_record_is_created(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->create('listing.jpg', 100, 'image/jpeg');

        $response = $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/product/upload', [
                'product_id' => $product->id,
                'image' => $file,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'image_path' => $response->json('data.image_path'),
        ]);
    }
}
