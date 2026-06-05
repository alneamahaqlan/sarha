<?php

namespace App\Services\Tracking;

use App\Enums\PixelProvider;
use App\Models\Clinic;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the TrackingContext for a request. The single place that decides
 * whether — and which — pixels load. Keep all the gating rules here.
 *
 * Gate order (every condition must pass or we return disabled()):
 *   1. Global feature flag `tracking.feature_enabled` is on.
 *   2. A trackable subject (clinic) resolved and its status is 'active'.
 *   3. It has at least one enabled, well-formed pixel.
 */
class TrackingContextResolver
{
    private const CACHE_TTL = 600; // 10 min; busted on clinic tracking update

    public function featureEnabled(): bool
    {
        return (bool) SystemSetting::get('tracking.feature_enabled', false);
    }

    public function consentRequired(): bool
    {
        return (bool) SystemSetting::get('tracking.consent_enabled', true);
    }

    /**
     * Resolve for a clinic-scoped public page.
     *
     * @param bool $isSensitivePage true on booking/confirmation pages → the
     *        page URL sent to pixels is sanitised so a medical service in
     *        the path can never leak.
     */
    public function forClinicSlug(?string $slug, bool $isSensitivePage = false): TrackingContext
    {
        if (! $this->featureEnabled()) {
            return TrackingContext::disabled();
        }

        // Platform-wide pixels load on every public page.
        $pixels = $this->platformPixels();
        $advancedMatching = false;
        $clinicId = null;

        // On a clinic page, merge in that clinic's approved pixels.
        if ($slug !== null && $slug !== '') {
            $config = $this->clinicTrackingConfig($slug);
            if ($config !== null && ($config['status'] ?? null) === 'active') {
                $pixels = $this->mergePixels($pixels, $this->normalizePixels($config['pixels'] ?? []));
                $advancedMatching = (bool) ($config['advanced_matching'] ?? false);
                $clinicId = $config['id'] ?? null;
            }
        }

        return $this->make($pixels, $advancedMatching, $isSensitivePage, $clinicId);
    }

    /**
     * Resolve from an already-loaded Clinic model — used by the booking
     * confirmation page, which has no {slug} route param. Merges platform
     * pixels with the clinic's. Always treated as sensitive.
     */
    public function forClinic(?Clinic $clinic, bool $isSensitivePage = false): TrackingContext
    {
        if (! $this->featureEnabled()) {
            return TrackingContext::disabled();
        }

        $pixels = $this->platformPixels();
        $advancedMatching = false;
        $clinicId = null;

        if ($clinic !== null && $clinic->tracking_status === 'active') {
            $pixels = $this->mergePixels($pixels, $this->normalizePixels($clinic->tracking_pixels ?? []));
            $advancedMatching = (bool) $clinic->advanced_matching_optin;
            $clinicId = $clinic->id;
        }

        return $this->make($pixels, $advancedMatching, $isSensitivePage, $clinicId);
    }

    private function make(array $pixels, bool $advancedMatching, bool $isSensitivePage, ?int $clinicId): TrackingContext
    {
        if ($pixels === []) {
            return TrackingContext::disabled();
        }

        $consent = $this->consentPayload();

        return new TrackingContext(
            enabled: true,
            pixels: $pixels,
            advancedMatching: $advancedMatching,
            sanitizeUrl: $isSensitivePage,
            consentRequired: $this->consentRequired(),
            clinicId: $clinicId,
            consentText: $consent['text'],
            privacyUrl: $consent['privacy_url'],
        );
    }

    /** Admin-managed pixels that load on every public page. */
    private function platformPixels(): array
    {
        return $this->normalizePixels(SystemSetting::get('tracking.platform_pixels', []) ?? []);
    }

    /** Union of two pixel lists, deduped by provider+id. */
    private function mergePixels(array $a, array $b): array
    {
        $seen = [];
        $out = [];
        foreach (array_merge($a, $b) as $p) {
            $key = $p['provider']->value . ':' . $p['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $p;
        }
        return $out;
    }

    /** Admin-editable consent copy + privacy link (localised), with defaults. */
    private function consentPayload(): array
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        $default = $locale === 'en'
            ? 'We use cookies to measure ad performance and improve your experience. By clicking "Accept" you agree.'
            : 'نستخدم ملفات تعريف الارتباط (الكوكيز) لقياس أداء الإعلانات وتحسين تجربتك. بالضغط على «قبول» فإنك توافق على ذلك.';

        $text = SystemSetting::get('tracking.consent_text_' . $locale);
        $url  = SystemSetting::get('tracking.privacy_url');

        return [
            'text'        => is_string($text) && $text !== '' ? $text : $default,
            'privacy_url' => is_string($url) && $url !== '' ? $url : null,
        ];
    }

    /**
     * Minimal, cached tracking config for a clinic by slug. Only the columns
     * we need — keeps this off the hot path of the full clinic query.
     */
    private function clinicTrackingConfig(string $slug): ?array
    {
        return Cache::remember("clinic:tracking:{$slug}", self::CACHE_TTL, function () use ($slug) {
            $clinic = Clinic::query()
                ->where('slug', $slug)
                ->first(['id', 'slug', 'status', 'tracking_status', 'tracking_pixels', 'advanced_matching_optin']);

            if ($clinic === null) {
                return null;
            }

            return [
                'id'                => $clinic->id,
                'status'            => $clinic->tracking_status,
                'pixels'            => $clinic->tracking_pixels ?? [],
                'advanced_matching' => (bool) $clinic->advanced_matching_optin,
            ];
        });
    }

    /** Drop pixels that are disabled or no longer pass strict validation. */
    private function normalizePixels(array $raw): array
    {
        $out = [];

        foreach ($raw as $row) {
            if (! is_array($row) || ! isset($row['provider'], $row['id'])) {
                continue;
            }
            if (array_key_exists('enabled', $row) && ! $row['enabled']) {
                continue;
            }

            $provider = PixelProvider::tryFrom((string) $row['provider']);
            $id = trim((string) $row['id']);

            // Defence-in-depth: never trust stored data — re-validate.
            if ($provider === null || ! $provider->isValidId($id)) {
                continue;
            }

            $out[] = ['provider' => $provider, 'id' => $id];
        }

        return $out;
    }

    /** Bust the per-clinic cache after a tracking-settings change. */
    public static function forget(string $slug): void
    {
        Cache::forget("clinic:tracking:{$slug}");
    }
}
