<?php

namespace App\Services;

class GoogleMapsService
{
    /**
     * Parse Google Maps URL and extract [lat, lng].
     *
     * Supports four common formats:
     *   1) ".../@24.7136,46.6753,15z"           — pin in URL
     *   2) ".../?q=24.7136,46.6753"              — q parameter
     *   3) ".../!3d24.7136!4d46.6753!"            — embed string
     *   4) "https://goo.gl/maps/XXXX" / short URL — must be resolved upstream
     *
     * @return array{lat: float, lng: float}|null
     */
    public function extractCoordinatesFromUrl(string $url): ?array
    {
        // Format 1: @lat,lng,zoom
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        // Format 2: ?q=lat,lng  (or &q=lat,lng or query=lat,lng)
        if (preg_match('/[?&](?:q|query)=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        // Format 3: !3dLAT!4dLNG  (Google embed format)
        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url, $m)) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        // Format 4: ll=lat,lng (older formats)
        if (preg_match('/[?&]ll=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        return null;
    }

    public function isValidSaudiCoordinate(float $lat, float $lng): bool
    {
        return $lat >= 16.0 && $lat <= 33.0  // Saudi latitude range
            && $lng >= 34.0 && $lng <= 56.0; // Saudi longitude range
    }

    public function staticMapUrl(float $lat, float $lng, int $zoom = 15, string $size = '600x300'): string
    {
        $key = config('services.google_maps.key');
        $params = http_build_query([
            'center'  => "$lat,$lng",
            'zoom'    => $zoom,
            'size'    => $size,
            'markers' => "color:teal|$lat,$lng",
            'key'     => $key,
        ]);
        return "https://maps.googleapis.com/maps/api/staticmap?$params";
    }

    public function directionsUrl(float $lat, float $lng): string
    {
        return "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}";
    }
}
