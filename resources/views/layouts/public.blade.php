@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $dir = $isRtl ? 'rtl' : 'ltr';
    $altLocale = $isRtl ? 'en' : 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('site.brand')) — @lang('site.tagline')</title>
    <meta name="description" content="@yield('description', __('site.meta_description'))">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph (pages override via @section('og_*'); image falls back to the platform default) --}}
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', __('site.brand'))">
    <meta property="og:description" content="@yield('og_description', __('site.meta_description'))">
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.png'))">
    <meta property="og:url" content="{{ url()->current() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Brand fonts: Readex Pro (headings), IBM Plex Sans Arabic (body), Inter (latin), JetBrains Mono (numerics/code) --}}
    <link href="https://fonts.googleapis.com/css2?family=Readex+Pro:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'IBM Plex Sans Arabic', 'Inter', ui-sans-serif, system-ui, sans-serif; }
        h1, h2, h3, h4 { font-family: 'Readex Pro', 'IBM Plex Sans Arabic', sans-serif; }
    </style>

    {{-- Marketing tracking foundation (Consent Mode + anonymized events).
         Inert unless this clinic's pixels are approved + the feature is on. --}}
    @include('partials.tracking.bootstrap')
    @include('partials.tracking.pixels')

    @stack('head')
</head>
<body class="bg-cream antialiased">

{{-- Navigation (admin-managed links via navigation_links + LayoutComposer) --}}
@include('layouts.partials.header')

{{-- Flash messages --}}
@if(session('success') || session('error'))
    <div id="flash-banner"
         class="{{ session('error') ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' }} border-b px-4 py-3 text-center text-sm transition-opacity duration-500">
        {{ session('error') ?: session('success') }}
        <button type="button" onclick="document.getElementById('flash-banner').remove()"
                class="ms-2 text-xs opacity-60 hover:opacity-100" aria-label="@lang('site.flash_dismiss')">✕</button>
    </div>
    <script>
        (function () {
            var el = document.getElementById('flash-banner');
            if (!el) return;
            setTimeout(function () {
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 500);
            }, 3000);
        })();
    </script>
@endif

@yield('content')

{{-- Footer (admin-managed columns + contact/social via LayoutComposer) --}}
@include('layouts.partials.footer')

{{-- Global AI chat widget --}}
@livewire('ai-chat')

{{-- Add-to-cart OTP modal — guests only (logged-in customers add directly). --}}
@guest('web')
    @include('public.partials.cart-otp-modal')
@endguest

{{-- Compare tray (populated client-side from localStorage).
     Type-aware: one bucket per item type (clinic/service/offer/package), so a
     comparison is always same-type. The tray shows whichever bucket the visitor
     last touched. --}}
<div id="compare-bar" class="fixed bottom-[calc(5rem+env(safe-area-inset-bottom))] sm:bottom-4 left-1/2 -translate-x-1/2 z-50 hidden w-[calc(100%-2rem)] max-w-md">
    <div class="flex items-center gap-3 rounded-xl bg-gray-900 px-4 py-3 text-white shadow-lg">
        <span class="text-sm">
            <span id="compare-count">0</span> <span id="compare-label">{{ __('site.compare_selected') }}</span>
        </span>
        <a id="compare-go" href="#" class="ms-auto rounded-lg bg-sage-500 px-4 py-2 text-sm font-semibold hover:bg-sage-600">
            @lang('site.compare_now')
        </a>
        <button id="compare-clear" type="button" class="text-sm text-gray-300 hover:text-white">
            @lang('site.compare_clear')
        </button>
    </div>
</div>

@php
    $compareTypes  = ['clinic', 'service', 'offer', 'package'];
    $compareLabels = [
        'clinic'  => __('site.compare_selected_clinics'),
        'service' => __('site.compare_selected_services'),
        'offer'   => __('site.compare_selected_offers'),
        'package' => __('site.compare_selected_packages'),
    ];
@endphp
<script>
(function () {
    var TYPES  = @json($compareTypes);
    var LABELS = @json($compareLabels);
    var MAX = 3;
    var COMPARE_URL = @json(route('compare'));
    var MAX_MSG = @json(__('site.compare_max', ['max' => 3]));

    function keyFor(type) { return 'saerha_compare_' + type; }
    function read(type) {
        try { return JSON.parse(localStorage.getItem(keyFor(type))) || []; } catch (e) { return []; }
    }
    function write(type, list) { localStorage.setItem(keyFor(type), JSON.stringify(list)); }

    // Active type = whichever non-empty bucket the visitor last touched. On a
    // cold load with several buckets filled, fall back to the first non-empty.
    var activeType = null;
    function pickActiveType() {
        if (activeType && read(activeType).length) { return activeType; }
        for (var i = 0; i < TYPES.length; i++) {
            if (read(TYPES[i]).length) { return TYPES[i]; }
        }
        return null;
    }

    function render() {
        // Reflect the selected state on every toggle, matched by type + id so a
        // service toggle never lights up from a clinic bucket of the same id.
        document.querySelectorAll('[data-compare-id]').forEach(function (btn) {
            var type = btn.dataset.compareType || 'clinic';
            var on = read(type).some(function (i) { return String(i.id) === btn.dataset.compareId; });
            btn.classList.toggle('is-selected', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        var bar = document.getElementById('compare-bar');
        if (!bar) { return; }
        activeType = pickActiveType();
        if (!activeType) { bar.classList.add('hidden'); return; }

        var list = read(activeType);
        bar.classList.toggle('hidden', list.length === 0);
        document.getElementById('compare-count').textContent = list.length;
        document.getElementById('compare-label').textContent = LABELS[activeType] || '';
        var go = document.getElementById('compare-go');
        var ids = list.map(function (i) { return i.id; }).join(',');
        go.href = COMPARE_URL + '?type=' + encodeURIComponent(activeType) + '&ids=' + encodeURIComponent(ids);
        var disabled = list.length < 2;
        go.classList.toggle('opacity-50', disabled);
        go.classList.toggle('pointer-events-none', disabled);
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-compare-id]');
        if (toggle) {
            e.preventDefault();
            e.stopPropagation();
            var type = toggle.dataset.compareType || 'clinic';
            var list = read(type);
            var id = toggle.dataset.compareId;
            var idx = list.findIndex(function (i) { return String(i.id) === id; });
            if (idx >= 0) {
                list.splice(idx, 1);
            } else {
                if (list.length >= MAX) { alert(MAX_MSG); return; }
                list.push({ id: id, name: toggle.dataset.compareName || '' });
            }
            write(type, list);
            activeType = type; // last touched wins the tray
            render();
            return;
        }
        if (e.target.closest('#compare-clear')) {
            if (activeType) { write(activeType, []); }
            render();
        }
    });

    document.addEventListener('DOMContentLoaded', render);
    render();
})();
</script>

{{-- Clinic action-button click tracking (fire-and-forget via sendBeacon) --}}
<script>
(function () {
    var TRACK_URL = @json(route('track.click'));
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-track][data-clinic]');
        if (!el) return;
        // First-party stat (sendBeacon).
        if (navigator.sendBeacon) {
            try {
                var fd = new FormData();
                fd.append('type', el.dataset.track);
                fd.append('clinic', el.dataset.clinic);
                navigator.sendBeacon(TRACK_URL, fd);
            } catch (err) {}
        }
        // Anonymized marketing event (only fires when tracking is active).
        if (window.sarhaTrack) {
            var EVENTS = { booking: 'click_book', call: 'click_call', whatsapp: 'click_whatsapp' };
            var ev = EVENTS[el.dataset.track];
            if (ev) window.sarhaTrack(ev, { clinic_id: Number(el.dataset.clinic) });
        }
    }, true);
})();
</script>

@include('partials.tracking.consent-banner')

@stack('scripts')

</body>
</html>
