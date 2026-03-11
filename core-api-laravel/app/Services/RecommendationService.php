<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RecommendationService
{
    public function getRecommendations($userId)
    {
        $url = config('services.recommendation.url');

        $response = Http::timeout(2)->get($url . "/api/recommendations/" . $userId);

        if ($response->successful()) {
            return $response->json()['recommendations'];
        }

        return [];
    }
}
