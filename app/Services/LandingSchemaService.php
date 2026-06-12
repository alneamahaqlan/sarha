<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\LandingPage;

/**
 * Builds a sensible schema.org JSON-LD payload per landing-page type when the
 * admin hasn't supplied an explicit override. Kept minimal + safe (no health
 * context beyond the public business name) — enriched in later passes.
 */
class LandingSchemaService
{
    public static function for(LandingPage $page, ?Clinic $clinic = null): ?array
    {
        $title = $page->title_ar ?: $page->seo_title_ar ?: $page->title_en;

        // FAQ blocks contribute a FAQPage graph node when present.
        $faq = self::faqFromBlocks($page);

        $base = match ($page->type) {
            'clinic', 'custom' => $clinic ? self::business($clinic) : null,
            'offer'            => $clinic ? self::business($clinic) : null,
            default            => $title ? [
                '@context' => 'https://schema.org',
                '@type'    => 'WebPage',
                'name'     => $title,
                'url'      => route('landing.show', $page->slug),
            ] : null,
        };

        if ($faq && $base) {
            return ['@context' => 'https://schema.org', '@graph' => [$base, $faq]];
        }

        return $faq ?: $base;
    }

    private static function business(Clinic $clinic): array
    {
        $schema = [
            '@context'  => 'https://schema.org',
            '@type'     => 'MedicalBusiness',
            'name'      => $clinic->name,
            'telephone' => $clinic->phone,
            'url'       => url()->current(),
            'address'   => [
                '@type'           => 'PostalAddress',
                'addressLocality' => $clinic->city?->name,
                'addressCountry'  => 'SA',
            ],
        ];

        if ($clinic->latitude && $clinic->longitude) {
            $schema['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $clinic->latitude,
                'longitude' => $clinic->longitude,
            ];
        }

        if (($clinic->google_reviews_count ?? 0) > 0) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => round((float) $clinic->google_reviews_avg_rating, 1),
                'reviewCount' => (int) $clinic->google_reviews_count,
            ];
        }

        return $schema;
    }

    private static function faqFromBlocks(LandingPage $page): ?array
    {
        $faqBlock = $page->blocks->firstWhere('type', 'faq');
        $items = collect($faqBlock?->config['items'] ?? [])
            ->filter(fn ($i) => filled($i['q'] ?? null) && filled($i['a'] ?? null))
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items->map(fn ($i) => [
                '@type'          => 'Question',
                'name'           => $i['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']],
            ])->all(),
        ];
    }
}
