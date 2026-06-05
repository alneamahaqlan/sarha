@inject('tracking', 'App\Services\Tracking\TrackingContext')

@if ($tracking->hasPixels())
@php($loaderJs = app('App\Services\Tracking\PixelSnippetRenderer')->loaderJs($tracking))
{{--
    Defines window.sarhaLoadPixels() — the actual pixel initialisers. It is
    NEVER called before consent: the consent banner calls it on "accept",
    and a returning visitor who already granted consent triggers it below.
    Idempotent via the __sarhaPixelsLoaded guard.
--}}
<script>
window.sarhaLoadPixels = function () {
    if (window.__sarhaPixelsLoaded) return;
    window.__sarhaPixelsLoaded = true;
    {!! $loaderJs !!}
};

// Which providers are active on this page (drives the event bridge below).
window.__sarhaProviders = @json($tracking->providerKeys());

// Bridge: map an anonymized sarhaTrack event to each provider's STANDARD
// conversion event. Fires only after the pixels are loaded (post-consent).
// No payload is forwarded — these carry zero PII / health context.
window.__sarhaPixelFire = function (event) {
    if (!window.__sarhaPixelsLoaded) return;
    var MAP = {
        submit_booking: { meta: 'Lead',             tiktok: 'SubmitForm',     snapchat: 'SIGN_UP',       gtag: 'generate_lead' },
        click_book:     { meta: 'InitiateCheckout', tiktok: 'InitiateCheckout', snapchat: 'START_CHECKOUT', gtag: 'begin_checkout' },
        view_clinic:    { meta: 'ViewContent',      tiktok: 'ViewContent',    snapchat: 'VIEW_CONTENT',  gtag: 'view_item' },
        view_offer:     { meta: 'ViewContent',      tiktok: 'ViewContent',    snapchat: 'VIEW_CONTENT',  gtag: 'view_item' },
        view_service:   { meta: 'ViewContent',      tiktok: 'ViewContent',    snapchat: 'VIEW_CONTENT',  gtag: 'view_item' }
    };
    var m = MAP[event];
    if (!m) return;
    (window.__sarhaProviders || []).forEach(function (p) {
        try {
            if (p === 'meta' && window.fbq && m.meta) fbq('track', m.meta);
            else if (p === 'tiktok' && window.ttq && m.tiktok) ttq.track(m.tiktok);
            else if (p === 'snapchat' && window.snaptr && m.snapchat) snaptr('track', m.snapchat);
            else if ((p === 'ga4' || p === 'google_ads') && window.gtag && m.gtag) gtag('event', m.gtag);
            // X/Twitter conversions need advertiser-defined event IDs — skipped.
        } catch (e) {}
    });
};

try {
    if (document.cookie.indexOf('sarha_consent=granted') !== -1) {
        window.sarhaLoadPixels();
    }
} catch (e) {}
</script>
@endif
