<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\GoogleReview;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    private const PLACE_DETAILS_ENDPOINT = 'https://maps.googleapis.com/maps/api/place/details/json';

    public function __construct(private readonly ?string $apiKey = null) {}

    private function key(): ?string
    {
        return $this->apiKey ?? config('services.google_maps.key');
    }

    /**
     * Fetch reviews for a Place ID and sync them into google_reviews.
     *
     * @return array{success: bool, fetched: int, message: ?string}
     */
    public function syncReviews(Clinic $clinic): array
    {
        if (! $clinic->google_place_id) {
            return ['success' => false, 'fetched' => 0, 'message' => 'No Place ID configured'];
        }

        if (! $this->key()) {
            return ['success' => false, 'fetched' => 0, 'message' => 'GOOGLE_MAPS_API_KEY missing'];
        }

        try {
            $response = Http::timeout(15)->get(self::PLACE_DETAILS_ENDPOINT, [
                'place_id' => $clinic->google_place_id,
                'fields'   => 'reviews,rating,user_ratings_total',
                'language' => 'ar',
                'key'      => $this->key(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('GooglePlaces fetch failed', ['error' => $e->getMessage(), 'clinic' => $clinic->id]);
            return ['success' => false, 'fetched' => 0, 'message' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return ['success' => false, 'fetched' => 0, 'message' => 'HTTP ' . $response->status()];
        }

        $data = $response->json();
        if (($data['status'] ?? '') !== 'OK') {
            return ['success' => false, 'fetched' => 0, 'message' => $data['error_message'] ?? $data['status'] ?? 'Unknown'];
        }

        $reviews = $data['result']['reviews'] ?? [];
        $count = 0;

        foreach ($reviews as $review) {
            GoogleReview::updateOrCreate(
                [
                    'clinic_id'        => $clinic->id,
                    'google_review_id' => $review['time'] . '-' . substr(md5($review['author_name'] ?? ''), 0, 8),
                ],
                [
                    'reviewer_name'  => $review['author_name'] ?? null,
                    'reviewer_photo' => $review['profile_photo_url'] ?? null,
                    'rating'         => (int) ($review['rating'] ?? 0),
                    'review_text'    => $review['text'] ?? null,
                    'reviewed_at'    => Carbon::createFromTimestamp($review['time'] ?? time()),
                    'is_visible'    => true,
                ]
            );
            $count++;
        }

        return ['success' => true, 'fetched' => $count, 'message' => null];
    }
}
