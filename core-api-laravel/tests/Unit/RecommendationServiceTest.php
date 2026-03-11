<?php

namespace Tests\Unit;

use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_recommendations_are_cached_for_five_minutes(): void
    {
        Http::fake([
            '*' => Http::response([
                'recommendations' => [
                    ['product_id' => 10],
                ],
            ], 200),
        ]);

        $service = app(RecommendationService::class);

        $firstResponse = $service->getRecommendations(7);
        $secondResponse = $service->getRecommendations(7);

        $this->assertSame([['product_id' => 10]], $firstResponse);
        $this->assertSame($firstResponse, $secondResponse);
        Http::assertSentCount(1);
        $this->assertTrue(Cache::has('recommendations_user_7'));
    }

    public function test_recommendation_service_returns_empty_array_when_request_fails(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $service = app(RecommendationService::class);

        $this->assertSame([], $service->getRecommendations(99));
    }
}
