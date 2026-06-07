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

{{-- Compare tray (populated client-side from localStorage) --}}
<div id="compare-bar" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 hidden w-[calc(100%-2rem)] max-w-md">
    <div class="flex items-center gap-3 rounded-xl bg-gray-900 px-4 py-3 text-white shadow-lg">
        <span class="text-sm">
            <span id="compare-count">0</span> {{ __('site.compare_selected') }}
        </span>
        <a id="compare-go" href="#" class="ms-auto rounded-lg bg-sage-500 px-4 py-2 text-sm font-semibold hover:bg-sage-600">
            @lang('site.compare_now')
        </a>
        <button id="compare-clear" type="button" class="text-sm text-gray-300 hover:text-white">
            @lang('site.compare_clear')
        </button>
    </div>
</div>

<script>
(function () {
    var KEY = 'saerha_compare';
    var MAX = 3;
    var COMPARE_URL = @json(route('compare'));
    var MAX_MSG = @json(__('site.compare_max', ['max' => 3]));

    function read() {
        try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; }
    }
    function write(list) { localStorage.setItem(KEY, JSON.stringify(list)); }

    function render() {
        var list = read();
        document.querySelectorAll('[data-compare-id]').forEach(function (btn) {
            var on = list.some(function (i) { return String(i.id) === btn.dataset.compareId; });
            btn.classList.toggle('is-selected', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        var bar = document.getElementById('compare-bar');
        if (!bar) return;
        document.getElementById('compare-count').textContent = list.length;
        bar.classList.toggle('hidden', list.length === 0);
        var go = document.getElementById('compare-go');
        var ids = list.map(function (i) { return i.id; }).join(',');
        go.href = COMPARE_URL + '?ids=' + encodeURIComponent(ids);
        var disabled = list.length < 2;
        go.classList.toggle('opacity-50', disabled);
        go.classList.toggle('pointer-events-none', disabled);
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-compare-id]');
        if (toggle) {
            e.preventDefault();
            e.stopPropagation();
            var list = read();
            var id = toggle.dataset.compareId;
            var idx = list.findIndex(function (i) { return String(i.id) === id; });
            if (idx >= 0) {
                list.splice(idx, 1);
            } else {
                if (list.length >= MAX) { alert(MAX_MSG); return; }
                list.push({ id: id, name: toggle.dataset.compareName || '' });
            }
            write(list);
            render();
            return;
        }
        if (e.target.closest('#compare-clear')) {
            write([]);
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
