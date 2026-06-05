<?php

namespace App\Enums;

/**
 * The advertising/analytics pixels a clinic (or the platform) can attach.
 * This enum is the SINGLE source of truth for provider metadata — adding a
 * new platform later is one case + one regex + one label, with no other
 * architectural change (see docs/tracking/gtm-integration-plan.md).
 *
 * We deliberately render the official pixel snippets ourselves (never a
 * third-party GTM container), so the set of providers is closed and vetted.
 */
enum PixelProvider: string
{
    case GA4        = 'ga4';         // Google Analytics 4  — G-XXXXXXXX
    case GOOGLE_ADS = 'google_ads';  // Google Ads          — AW-XXXXXXXXX
    case META       = 'meta';        // Meta (FB/Instagram) — numeric pixel id
    case SNAPCHAT   = 'snapchat';    // Snapchat Pixel      — UUID
    case TIKTOK     = 'tiktok';      // TikTok Pixel        — alphanumeric code
    case X          = 'x';           // X / Twitter Pixel   — alphanumeric code

    /** Strict format for the provider's ID. Anchored — used for validation. */
    public function pattern(): string
    {
        return match ($this) {
            self::GA4        => '/^G-[A-Z0-9]{4,12}$/',
            self::GOOGLE_ADS => '/^AW-[0-9]{6,12}$/',
            self::META       => '/^[0-9]{6,20}$/',
            self::SNAPCHAT   => '/^[0-9a-fA-F-]{36}$/',
            self::TIKTOK     => '/^[A-Z0-9]{15,30}$/',
            self::X          => '/^[a-z0-9]{4,12}$/',
        };
    }

    /** Arabic display label for the admin/clinic panels. */
    public function label(): string
    {
        return match ($this) {
            self::GA4        => 'Google Analytics (GA4)',
            self::GOOGLE_ADS => 'Google Ads',
            self::META       => 'Meta (فيسبوك/انستقرام)',
            self::SNAPCHAT   => 'Snapchat',
            self::TIKTOK     => 'TikTok',
            self::X          => 'X (تويتر)',
        };
    }

    /** Example to show in the input placeholder. */
    public function example(): string
    {
        return match ($this) {
            self::GA4        => 'G-XXXXXXXXXX',
            self::GOOGLE_ADS => 'AW-123456789',
            self::META       => '123456789012345',
            self::SNAPCHAT   => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            self::TIKTOK     => 'CXXXXXXXXXXXXXXXXXXX',
            self::X          => 'oxxxx',
        };
    }

    /** GA4 + Google Ads share a single gtag.js loader. */
    public function usesGtag(): bool
    {
        return $this === self::GA4 || $this === self::GOOGLE_ADS;
    }

    /** Validate a raw ID against this provider's format. */
    public function isValidId(string $id): bool
    {
        return (bool) preg_match($this->pattern(), trim($id));
    }

    /** The regex source without PHP delimiters — for client-side hinting. */
    public function jsPattern(): string
    {
        return trim($this->pattern(), '/');
    }

    /** Catalog row for the admin/clinic UI. */
    public function toArray(): array
    {
        return [
            'key'     => $this->value,
            'label'   => $this->label(),
            'example' => $this->example(),
            'pattern' => $this->jsPattern(),
        ];
    }

    /** Full provider catalog for the UI dropdowns. */
    public static function catalog(): array
    {
        return array_map(fn (self $p) => $p->toArray(), self::cases());
    }

    /** All provider keys — handy for `in:` validation and the UI. */
    public static function keys(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
