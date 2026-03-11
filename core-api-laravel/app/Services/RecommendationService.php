<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecommendationService
{
    public function getRecommendations($userId)
    {
        $url = config('services.recommendation.url');

        try {
            return Cache::remember(
                'recommendations_user_'.$userId,
                300,
                function () use ($url, $userId) {
                    $response = Http::timeout(2)
                        ->retry(2, 100)
                        ->get($url.'/api/recommendations/'.$userId);

                    if ($response->successful()) {
                        return $response->json()['recommendations'] ?? [];
                    }

                    Log::warning('recommendation_request_failed', [
                        'user_id' => $userId,
                        'status' => $response->status(),
                    ]);

                    return [];
                }
            );
        } catch (\Exception $e) {
            Log::warning('recommendation_service_failed', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);
        }

        return [];
    }
}
