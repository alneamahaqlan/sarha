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

    @stack('head')
</head>
<body class="bg-cream antialiased">

{{-- Navigation --}}
<nav class="bg-white shadow-sm sticky top-0 z-50" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-2">
                {{-- Mobile hamburger --}}
                <button type="button" @click="open = !open"
                        class="md:hidden p-2 -ms-2 text-gray-600 hover:text-sage-600"
                        :aria-expanded="open.toString()" aria-label="@lang('site.menu')">
                    <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <x-logo :size="40" />
                </a>
            </div>

            <div class="hidden md:flex items-center gap-6">
                <a href="{{ route('search') }}" class="text-gray-600 hover:text-sage-600 transition-colors">@lang('site.nav_search')</a>
                <a href="{{ route('quotes.board') }}" class="text-gray-600 hover:text-sage-600 transition-colors">@lang('site.nav_quotes')</a>
            </div>

            <div class="flex items-center gap-3">
                {{-- Language switcher --}}
                <a href="{{ route('lang.switch', $altLocale) }}"
                   class="text-sm text-gray-600 hover:text-sage-600 transition-colors px-2 py-1 border border-gray-200 rounded-lg"
                   title="@lang('site.language')">
                    {{ $altLocale === 'en' ? 'English' : 'العربية' }}
                </a>

                @auth('web')
                    <a href="{{ route('account.show') }}"
                       class="text-sm text-gray-700 hover:text-sage-600 transition-colors flex items-center gap-1.5">
                        <span class="w-7 h-7 rounded-full bg-sage-100 text-sage-700 flex items-center justify-center text-xs font-bold">
                            {{ mb_substr(auth('web')->user()->name ?: 'م', 0, 1) }}
                        </span>
                        <span class="hidden sm:inline">{{ auth('web')->user()->name ?: __('site.account_title') }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 hover:text-red-500">@lang('site.nav_logout')</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="bg-sage-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-sage-700 transition-colors">
                        @lang('site.nav_login')
                    </a>
                @endauth
            </div>
        </div>

        {{-- Mobile menu panel --}}
        <div x-show="open" x-cloak @click.outside="open = false"
             class="md:hidden border-t border-gray-100 py-2">
            <a href="{{ route('search') }}" class="block px-2 py-2.5 text-gray-700 hover:text-sage-600">@lang('site.nav_search')</a>
            <a href="{{ route('quotes.board') }}" class="block px-2 py-2.5 text-gray-700 hover:text-sage-600">@lang('site.nav_quotes')</a>
            @auth('web')
                <a href="{{ route('account.show') }}" class="block px-2 py-2.5 text-gray-700 hover:text-sage-600">@lang('site.account_profile')</a>
                <a href="{{ route('account.bookings') }}" class="block px-2 py-2.5 text-gray-700 hover:text-sage-600">@lang('site.account_my_bookings')</a>
                <a href="{{ route('account.favorites') }}" class="block px-2 py-2.5 text-gray-700 hover:text-sage-600">@lang('site.account_my_favorites')</a>
            @else
                <a href="{{ route('login') }}" class="block px-2 py-2.5 text-gray-700 hover:text-sage-600">@lang('site.nav_login')</a>
            @endauth
        </div>
    </div>
</nav>

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

<footer class="bg-charcoal text-gray-400 mt-16">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <x-logo :size="44" :on-dark="true" class="mb-3" />
                <p class="text-sm leading-relaxed mt-3">@lang('site.footer_about')</p>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-3">@lang('site.footer_quick_links')</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('home') }}" class="hover:text-white transition-colors">@lang('site.breadcrumb_home')</a></li>
                    <li><a href="{{ route('search') }}" class="hover:text-white transition-colors">@lang('site.nav_search')</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-3">@lang('site.footer_for_clinics')</h4>
                <p class="text-sm mb-3">@lang('site.footer_for_clinics_desc')</p>
                <a href="{{ route('clinic.register') }}" class="bg-sage-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-sage-700 transition-colors inline-block">
                    @lang('site.nav_register_clinic')
                </a>
            </div>
        </div>
        <div class="border-t border-gray-800 mt-8 pt-6 text-center text-sm">
            {!! __('site.footer_rights', ['year' => date('Y')]) !!}
        </div>
    </div>
</footer>

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
        if (!el || !navigator.sendBeacon) return;
        try {
            var fd = new FormData();
            fd.append('type', el.dataset.track);
            fd.append('clinic', el.dataset.clinic);
            navigator.sendBeacon(TRACK_URL, fd);
        } catch (err) {}
    }, true);
})();
</script>

@stack('scripts')

</body>
</html>
