@inject('tracking', 'App\Services\Tracking\TrackingContext')

@if ($tracking->enabled)
{{--
    Tracking foundation — Consent Mode v2 defaults + the anonymized event
    helper. Emits NO network request and NO pixel by itself: pixel loaders
    and the consent banner are added in Phase 2. Rendered only when the
    current clinic has approved, well-formed pixels.

    SENSITIVE: window.sarhaTrack enforces a key allowlist so a patient's
    name / phone / medical service can never reach the dataLayer or a pixel.
    See docs/tracking/gtm-integration-plan.md.
--}}
<script>
(function () {
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    window.gtag = window.gtag || gtag;

    // Default everything DENIED until the visitor consents (Consent Mode v2).
    gtag('consent', 'default', {
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        analytics_storage: 'denied',
        wait_for_update: 500
    });

    // Page-level tracking flags consumed by the Phase 2 pixel loaders.
    window.sarhaTracking = {
        clinicId: {{ (int) $tracking->clinicId }},
        sanitizeUrl: {{ $tracking->sanitizeUrl ? 'true' : 'false' }},
        advancedMatching: {{ $tracking->advancedMatching ? 'true' : 'false' }}
    };

    // Anonymized event push. Hard allowlist of keys — anything else is
    // dropped so PII / health context cannot leak, even by mistake.
    var ALLOWED = {
        clinic_id: 1, clinic_slug: 1, city: 1,
        offer_id: 1, service_id: 1, source: 1,
        booking_ref: 1, value: 1
    };
    window.sarhaTrack = function (event, payload) {
        var clean = { event: String(event) };
        payload = payload || {};
        for (var k in payload) {
            if (Object.prototype.hasOwnProperty.call(payload, k) && ALLOWED[k]) {
                clean[k] = payload[k];
            }
        }
        window.dataLayer.push(clean);
        // Also fire the matching standard conversion event on each pixel
        // (no-op until pixels are loaded post-consent).
        if (window.__sarhaPixelFire) window.__sarhaPixelFire(String(event));
    };

    // Returning visitor who already granted consent → flip to granted now so
    // the Phase 2 loaders fire on this load too.
    try {
        if (document.cookie.indexOf('sarha_consent=granted') !== -1) {
            gtag('consent', 'update', {
                ad_storage: 'granted',
                ad_user_data: 'granted',
                ad_personalization: 'granted',
                analytics_storage: 'granted'
            });
        }
    } catch (e) {}
})();
</script>
@endif
