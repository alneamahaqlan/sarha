<?php

namespace App\Services\Tracking;

use App\Enums\PixelProvider;

/**
 * Immutable, request-scoped description of WHAT tracking the current page
 * should load. Built once per request by TrackingContextResolver and read
 * only by the tracking blade partials — no tracking logic lives in views.
 *
 * Empty/disabled by default: the partials render nothing unless `enabled`
 * is true, so an un-resolved or feature-off request is inert.
 */
class TrackingContext
{
    /**
     * @param array<int, array{provider: PixelProvider, id: string}> $pixels
     */
    public function __construct(
        public readonly bool $enabled = false,
        public readonly array $pixels = [],
        public readonly bool $advancedMatching = false,
        public readonly bool $sanitizeUrl = false,
        public readonly bool $consentRequired = true,
        public readonly ?int $clinicId = null,
        public readonly string $consentText = '',
        public readonly ?string $privacyUrl = null,
    ) {}

    /** A disabled context — renders nothing. */
    public static function disabled(): self
    {
        return new self(enabled: false);
    }

    public function hasPixels(): bool
    {
        return $this->enabled && $this->pixels !== [];
    }

    /** GA4 + Google Ads IDs (they share one gtag.js loader). */
    public function gtagIds(): array
    {
        return array_values(array_map(
            fn ($p) => $p['id'],
            array_filter($this->pixels, fn ($p) => $p['provider']->usesGtag()),
        ));
    }

    /** Pixels that have their own loader (Meta, Snap, TikTok, X). */
    public function standalonePixels(): array
    {
        return array_values(array_filter(
            $this->pixels,
            fn ($p) => ! $p['provider']->usesGtag(),
        ));
    }

    /** Distinct provider keys (e.g. ['meta','ga4']) — drives event mapping. */
    public function providerKeys(): array
    {
        return array_values(array_unique(array_map(
            fn ($p) => $p['provider']->value,
            $this->pixels,
        )));
    }

    /** First Meta pixel ID, if any — used for advanced matching. */
    public function metaPixelId(): ?string
    {
        foreach ($this->pixels as $p) {
            if ($p['provider'] === \App\Enums\PixelProvider::META) {
                return $p['id'];
            }
        }
        return null;
    }
}
