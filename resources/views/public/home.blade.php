@extends('layouts.public')

@section('title', __('site.brand'))

@section('content')

{{-- ===================== HERO ===================== --}}
<section class="relative overflow-hidden bg-gradient-to-br from-sage-800 via-sage-700 to-sage-900 animate-gradient text-white">
    {{-- grid pattern --}}
    <div class="absolute inset-0 opacity-[0.10] pointer-events-none"
         style="background-image:linear-gradient(rgba(255,255,255,.7) 1px,transparent 1px),linear-gradient(to right,rgba(255,255,255,.7) 1px,transparent 1px);background-size:64px 64px"></div>
    {{-- radial glows --}}
    <div class="absolute -top-28 -end-24 w-[28rem] h-[28rem] rounded-full bg-gold-primary/20 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -start-28 w-[28rem] h-[28rem] rounded-full bg-sage-400/25 blur-3xl pointer-events-none"></div>
    {{-- floating blobs --}}
    <div class="absolute top-20 start-8 w-24 h-24 bg-gold-primary/10 ring-1 ring-gold-primary/20 blob float hidden md:block pointer-events-none"></div>
    <div class="absolute bottom-24 end-12 w-16 h-16 bg-white/5 ring-1 ring-white/10 blob float float-delay-2 hidden md:block pointer-events-none"></div>
    {{-- floating AI neural motif --}}
    <svg class="absolute top-14 end-20 w-36 h-36 opacity-50 float float-delay hidden lg:block pointer-events-none" viewBox="0 0 90 90" aria-hidden="true">
        <line class="ai-line ai-line-1" x1="20" y1="30" x2="46" y2="14" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <line class="ai-line ai-line-2" x1="20" y1="30" x2="40" y2="52" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <line class="ai-line ai-line-3" x1="46" y1="14" x2="72" y2="34" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <line class="ai-line ai-line-2" x1="40" y1="52" x2="72" y2="34" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <circle class="ai-dot ai-dot-1" cx="20" cy="30" r="4" fill="#C9A961"/>
        <circle class="ai-dot ai-dot-2" cx="46" cy="14" r="3.5" fill="#E5D4A8"/>
        <circle class="ai-dot ai-dot-3" cx="72" cy="34" r="3.5" fill="#E5D4A8"/>
        <circle class="ai-dot ai-dot-2" cx="40" cy="52" r="3" fill="#C9A961"/>
    </svg>

    <div class="relative max-w-5xl mx-auto px-4 pt-20 pb-28 md:pt-24 md:pb-32 text-center">
        {{-- badge --}}
        <div class="reveal inline-flex items-center gap-2 bg-white/10 ring-1 ring-white/20 backdrop-blur-sm rounded-full ps-2 pe-4 py-1.5 text-sm text-sage-50 mb-6">
            <span class="relative flex h-2.5 w-2.5">
                <span class="absolute inline-flex h-full w-full rounded-full bg-gold-primary opacity-75" style="animation:pulse-ring 1.8s cubic-bezier(0,0,0.2,1) infinite"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-gold-primary"></span>
            </span>
            @lang('site.hero_badge')
        </div>

        @if($user)
            <p class="reveal text-sage-100 text-base md:text-lg mb-3" style="--reveal-delay:80ms">{{ __('site.greeting_welcome', ['name' => $user->name ?: '']) }}</p>
        @endif

        <h1 class="reveal font-display text-4xl md:text-6xl font-bold leading-[1.1] mb-5" style="--reveal-delay:120ms">
            @lang('site.hero_title')
        </h1>
        <p class="reveal text-sage-200 text-lg md:text-xl mb-10 max-w-2xl mx-auto" style="--reveal-delay:200ms">@lang('site.hero_subtitle')</p>

        {{-- search --}}
        <form action="{{ route('search') }}" method="GET"
              class="reveal bg-white rounded-2xl p-3 shadow-2xl ring-1 ring-black/5 max-w-3xl mx-auto" style="--reveal-delay:280ms"
              x-data="{ focused: false }" :class="focused ? 'ring-4 ring-gold-primary/40' : ''">
            <div class="flex flex-col md:flex-row gap-2.5">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 start-3 flex items-center text-gray-400 pointer-events-none"><x-icon name="search" class="w-5 h-5" /></span>
                    <input type="text" name="q" placeholder="@lang('site.search_placeholder')"
                           @focus="focused = true" @blur="focused = false"
                           class="w-full border border-gray-200 rounded-xl ps-11 pe-4 py-3.5 text-gray-800 focus:outline-none focus:ring-2 focus:ring-sage-500">
                </div>
                <select name="city" class="border border-gray-200 rounded-xl px-4 py-3.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-sage-500 md:min-w-44">
                    <option value="">@lang('site.search_all_cities')</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}">{{ $city->display_name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-sage-600 text-white px-8 py-3.5 rounded-xl font-semibold hover:bg-sage-700 active:scale-[0.98] transition-all whitespace-nowrap shadow-lg shadow-sage-900/30">
                    @lang('site.search_button')
                </button>
            </div>
        </form>

        {{-- nearest + trust chips --}}
        <div class="reveal mt-6 flex flex-col items-center gap-5" style="--reveal-delay:360ms">
            <button type="button" id="nearest-btn" onclick="saerhaFindNearest()"
                    class="inline-flex items-center gap-1.5 text-sage-100 hover:text-white text-sm font-medium transition-colors">
                <x-icon name="map-pin" class="w-4 h-4" />
                <span id="nearest-btn-label">@lang('site.use_my_location')</span>
            </button>
            <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-sage-100/90">
                <span class="inline-flex items-center gap-1.5"><x-icon name="check-circle" class="w-4 h-4 text-gold-soft" /> @lang('site.hero_chip_verified')</span>
                <span class="inline-flex items-center gap-1.5"><x-icon name="scale" class="w-4 h-4 text-gold-soft" /> @lang('site.hero_chip_prices')</span>
                <span class="inline-flex items-center gap-1.5"><x-icon name="cpu" class="w-4 h-4 text-gold-soft" /> @lang('site.hero_chip_ai')</span>
            </div>
        </div>
    </div>
</section>

{{-- ===================== STATS BAND ===================== --}}
<section class="px-4">
    <div class="max-w-6xl mx-auto -mt-14 relative z-10">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
            @foreach([
                ['icon' => 'building', 'value' => $stats['clinics'], 'label' => 'stats_clinics'],
                ['icon' => 'map-pin', 'value' => $stats['cities'], 'label' => 'stats_cities'],
                ['icon' => 'scale', 'value' => $stats['specialties'], 'label' => 'stats_specialties'],
                ['icon' => 'clipboard', 'value' => $stats['services'], 'label' => 'stats_services'],
            ] as $i => $stat)
                <div class="reveal reveal-zoom bg-white rounded-2xl shadow-lg shadow-sage-900/5 ring-1 ring-gray-100 p-5 text-center" style="--reveal-delay:{{ $i * 90 }}ms">
                    <div class="mx-auto mb-3 w-11 h-11 rounded-xl bg-sage-mist text-sage-primary flex items-center justify-center">
                        <x-icon :name="$stat['icon']" class="w-5 h-5" />
                    </div>
                    <div class="font-display text-3xl md:text-4xl font-bold text-sage-deep" data-count="{{ $stat['value'] }}">0</div>
                    <div class="text-sm text-gray-500 mt-1">@lang('site.' . $stat['label'])</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== CATEGORIES ===================== --}}
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <p class="reveal text-sm font-semibold tracking-widest text-gold-deep uppercase mb-2">@lang('site.categories_eyebrow')</p>
            <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">@lang('site.browse_categories')</h2>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-7 gap-3 md:gap-4">
            @foreach($categories->take(14) as $i => $category)
                <a href="{{ route('search', ['category' => $category->id]) }}"
                   class="reveal reveal-zoom group flex flex-col items-center justify-center p-4 bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 hover:shadow-xl hover:ring-gold-soft hover:-translate-y-1 transition-all duration-300 text-center"
                   style="--reveal-delay:{{ ($i % 7) * 60 }}ms">
                    <span class="mb-2.5 w-12 h-12 rounded-xl bg-sage-mist text-sage-primary flex items-center justify-center group-hover:bg-gold-whisper group-hover:text-gold-deep transition-colors">
                        <x-category-icon :emoji="$category->emoji" class="w-6 h-6" />
                    </span>
                    <span class="text-xs font-medium text-gray-700 group-hover:text-sage-deep">{{ $category->display_name }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== AI HIGHLIGHT (plum) ===================== --}}
<section class="relative overflow-hidden bg-gradient-to-br from-plum-deep via-plum-primary to-plum-deep animate-gradient text-white">
    <div class="absolute inset-0 opacity-[0.08] pointer-events-none"
         style="background-image:radial-gradient(rgba(255,255,255,.8) 1px,transparent 1px);background-size:26px 26px"></div>
    <div class="absolute -top-24 -start-20 w-96 h-96 rounded-full bg-plum-medium/30 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-28 -end-16 w-96 h-96 rounded-full bg-plum-soft/20 blur-3xl pointer-events-none"></div>

    <div class="relative max-w-6xl mx-auto px-4 py-20 grid md:grid-cols-2 gap-12 items-center">
        <div>
            <p class="reveal inline-flex items-center gap-2 text-sm font-semibold tracking-widest text-plum-soft uppercase mb-4">
                <span class="w-8 h-px bg-plum-soft"></span> @lang('site.ai_home_eyebrow')
            </p>
            <h2 class="reveal font-display text-3xl md:text-4xl font-bold leading-snug mb-4" style="--reveal-delay:80ms">@lang('site.ai_home_title')</h2>
            <p class="reveal text-plum-whisper/90 text-lg leading-relaxed mb-7" style="--reveal-delay:160ms">@lang('site.ai_home_desc')</p>
            <ul class="space-y-3 mb-8">
                @foreach(['ai_home_point1', 'ai_home_point2', 'ai_home_point3'] as $i => $point)
                    <li class="reveal flex items-center gap-3" style="--reveal-delay:{{ 220 + $i * 80 }}ms">
                        <span class="w-6 h-6 rounded-full bg-white/15 flex items-center justify-center shrink-0"><x-icon name="check-circle" class="w-4 h-4 text-gold-soft" /></span>
                        <span class="text-plum-whisper">@lang('site.' . $point)</span>
                    </li>
                @endforeach
            </ul>
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-ai-chat'))"
                    class="reveal inline-flex items-center gap-2 bg-white text-plum-deep px-7 py-3.5 rounded-xl font-bold hover:bg-plum-whisper active:scale-[0.98] transition-all shadow-xl shadow-plum-deep/30" style="--reveal-delay:480ms">
                <x-icon name="cpu" class="w-5 h-5" /> @lang('site.ai_home_cta')
            </button>
        </div>

        {{-- big pulsing AI mark --}}
        <div class="reveal reveal-zoom flex justify-center" style="--reveal-delay:160ms">
            <div class="relative w-64 h-64 rounded-full bg-white/5 ring-1 ring-white/10 flex items-center justify-center float">
                <div class="absolute inset-6 rounded-full border border-dashed border-white/15"></div>
                <svg width="170" height="170" viewBox="0 0 90 90" aria-hidden="true">
                    <path d="M 20 24 Q 20 20, 24 20 L 56 20 Q 62 20, 62 26 L 62 44 Q 62 58, 48 62 L 26 62 Q 20 62, 20 56 L 20 24 Z M 30 30 L 30 52 L 46 52 Q 52 52, 52 44 L 52 32 Q 52 30, 50 30 L 30 30 Z" fill="#FAF7F2"/>
                    <line class="ai-line ai-line-1" x1="62" y1="22" x2="76" y2="14" stroke="#E5D4A8" stroke-width="1.5" stroke-linecap="round"/>
                    <line class="ai-line ai-line-2" x1="62" y1="22" x2="80" y2="28" stroke="#E5D4A8" stroke-width="1.5" stroke-linecap="round"/>
                    <line class="ai-line ai-line-3" x1="76" y1="14" x2="80" y2="28" stroke="#C5B0D6" stroke-width="1.2" stroke-linecap="round" opacity="0.8"/>
                    <circle class="ai-dot ai-dot-1" cx="62" cy="22" r="4.5" fill="#C9A961"/>
                    <circle class="ai-dot ai-dot-2" cx="76" cy="14" r="3.5" fill="#E5D4A8"/>
                    <circle class="ai-dot ai-dot-3" cx="80" cy="28" r="3.5" fill="#E5D4A8"/>
                </svg>
            </div>
        </div>
    </div>
</section>

{{-- ===================== HOW IT WORKS ===================== --}}
<section class="py-16 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="reveal font-display text-3xl font-bold text-charcoal">@lang('site.how_it_works_title')</h2>
            <p class="reveal text-gray-500 mt-2" style="--reveal-delay:80ms">@lang('site.how_it_works_subtitle')</p>
        </div>
        <div class="relative grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- connecting line (desktop) --}}
            <div class="hidden md:block absolute top-10 inset-x-16 h-px bg-gradient-to-l from-transparent via-sage-soft to-transparent"></div>
            @foreach([['search', 'how_step_1'], ['scale', 'how_step_2'], ['phone', 'how_step_3']] as $i => [$icon, $key])
                <div class="reveal relative bg-white rounded-2xl ring-1 ring-gray-100 shadow-sm hover:shadow-lg transition-shadow p-7 text-center" style="--reveal-delay:{{ $i * 120 }}ms">
                    <div class="relative mx-auto mb-4 w-16 h-16 rounded-2xl bg-sage-mist text-sage-primary flex items-center justify-center">
                        <x-icon :name="$icon" class="w-8 h-8" />
                        <span class="absolute -top-2 -end-2 w-7 h-7 rounded-full bg-gradient-to-br from-gold-primary to-gold-deep text-white flex items-center justify-center text-sm font-bold shadow">{{ $i + 1 }}</span>
                    </div>
                    <h3 class="font-display font-bold text-charcoal mb-1.5">@lang('site.' . $key . '_title')</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">@lang('site.' . $key . '_desc')</p>
                </div>
            @endforeach
        </div>
        <div class="reveal mt-6 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-3 text-sm flex items-center justify-center gap-2">
            <x-icon name="warning" class="w-4 h-4 shrink-0" /> @lang('site.how_not_appointment_notice')
        </div>
    </div>
</section>

{{-- ===================== FEATURED CLINICS ===================== --}}
@if($featuredClinics->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8">
            <div>
                <p class="reveal text-sm font-semibold tracking-widest text-gold-deep uppercase mb-2">@lang('site.featured_eyebrow')</p>
                <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">@lang('site.featured_clinics')</h2>
            </div>
            <a href="{{ route('search', ['featured' => 1]) }}" class="reveal text-sage-600 text-sm font-semibold hover:text-sage-700 inline-flex items-center gap-1 whitespace-nowrap">
                @lang('site.view_all') <span class="rtl:rotate-180">→</span>
            </a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($featuredClinics as $i => $clinic)
                <div class="reveal" style="--reveal-delay:{{ ($i % 4) * 90 }}ms">
                    @include('public.partials.clinic-card', ['clinic' => $clinic, 'badgeContext' => $featuredClinics])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===================== TOP-RATED ===================== --}}
@if(($topRatedClinics ?? collect())->isNotEmpty())
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-2">
            <h2 class="reveal font-display text-3xl font-bold text-charcoal">@lang('site.top_rated_clinics')</h2>
            <a href="{{ route('search', ['sort' => 'top_rated']) }}" class="reveal text-sage-600 text-sm font-semibold hover:text-sage-700 inline-flex items-center gap-1 whitespace-nowrap">@lang('site.view_all') <span class="rtl:rotate-180">→</span></a>
        </div>
        <p class="reveal text-gray-500 text-sm mb-8">@lang('site.top_rated_subtitle')</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($topRatedClinics as $i => $clinic)
                <div class="reveal" style="--reveal-delay:{{ ($i % 3) * 100 }}ms">
                    @include('public.partials.clinic-card', ['clinic' => $clinic, 'badgeContext' => $topRatedClinics])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===================== BEST-PRICED ===================== --}}
@if(($bestPricedClinics ?? collect())->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-2">
            <h2 class="reveal font-display text-3xl font-bold text-charcoal">@lang('site.best_priced_clinics')</h2>
            <a href="{{ route('search', ['sort' => 'cheapest']) }}" class="reveal text-sage-600 text-sm font-semibold hover:text-sage-700 inline-flex items-center gap-1 whitespace-nowrap">@lang('site.view_all') <span class="rtl:rotate-180">→</span></a>
        </div>
        <p class="reveal text-gray-500 text-sm mb-8">@lang('site.best_priced_subtitle')</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($bestPricedClinics as $i => $clinic)
                <div class="reveal" style="--reveal-delay:{{ ($i % 3) * 100 }}ms">
                    @include('public.partials.clinic-card', ['clinic' => $clinic, 'badgeContext' => $bestPricedClinics])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===================== MAP ===================== --}}
@if(($mapClinics ?? collect())->isNotEmpty())
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-8">
            <h2 class="reveal font-display text-3xl font-bold text-charcoal">@lang('site.map_title')</h2>
            <p class="reveal text-gray-500 text-sm mt-1" style="--reveal-delay:80ms">@lang('site.map_subtitle')</p>
        </div>
        <div class="reveal" style="--reveal-delay:120ms">
            @include('public.partials.map', ['mapClinics' => $mapClinics, 'mapId' => 'home-map'])
        </div>
    </div>
</section>
@endif

{{-- ===================== CTA FOR CLINICS ===================== --}}
<section class="px-4 pb-20">
    <div class="max-w-6xl mx-auto">
        <div class="reveal reveal-zoom relative overflow-hidden rounded-3xl bg-gradient-to-br from-sage-700 to-sage-900 animate-gradient text-white text-center px-6 py-16 shine-host">
            <div class="absolute -top-16 -end-10 w-72 h-72 rounded-full bg-gold-primary/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-20 -start-10 w-72 h-72 rounded-full bg-sage-400/20 blur-3xl pointer-events-none"></div>
            <div class="relative max-w-2xl mx-auto">
                <h2 class="font-display text-3xl md:text-4xl font-bold mb-4">@lang('site.cta_title')</h2>
                <p class="text-sage-100 text-lg mb-8">@lang('site.cta_subtitle')</p>
                <a href="{{ route('clinic.register') }}" class="inline-flex items-center gap-2 bg-white text-sage-700 px-8 py-3.5 rounded-xl font-bold hover:bg-sage-50 active:scale-[0.98] transition-all shadow-xl">
                    <x-icon name="building" class="w-5 h-5" /> @lang('site.cta_button')
                </a>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
    // Scroll-reveal + animated counters
    (function () {
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var reveals = document.querySelectorAll('.reveal');
        if (reduce || !('IntersectionObserver' in window)) {
            reveals.forEach(function (el) { el.classList.add('in'); });
        } else {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            reveals.forEach(function (el) { io.observe(el); });
        }

        var counters = document.querySelectorAll('[data-count]');
        function runCount(el) {
            var target = parseInt(el.getAttribute('data-count'), 10) || 0;
            if (reduce) { el.textContent = target.toLocaleString(); return; }
            var dur = 1400, start = null;
            function tick(ts) {
                if (start === null) start = ts;
                var p = Math.min((ts - start) / dur, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.floor(eased * target).toLocaleString();
                if (p < 1) requestAnimationFrame(tick); else el.textContent = target.toLocaleString();
            }
            requestAnimationFrame(tick);
        }
        if ('IntersectionObserver' in window) {
            var cio = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) { runCount(en.target); cio.unobserve(en.target); }
                });
            }, { threshold: 0.5 });
            counters.forEach(function (c) { cio.observe(c); });
        } else {
            counters.forEach(runCount);
        }
    })();

    function saerhaFindNearest() {
        var btn = document.getElementById('nearest-btn');
        var label = document.getElementById('nearest-btn-label');
        if (!navigator.geolocation) { window.location.href = @json(route('search', ['sort' => 'nearest'])); return; }
        var original = label.textContent;
        label.textContent = @json(__('site.locating'));
        btn.disabled = true;
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                var url = new URL(@json(route('search')));
                url.searchParams.set('sort', 'nearest');
                url.searchParams.set('lat', pos.coords.latitude);
                url.searchParams.set('lng', pos.coords.longitude);
                window.location.href = url.toString();
            },
            function () {
                label.textContent = original;
                btn.disabled = false;
                alert(@json(__('site.location_denied')));
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }
</script>
@endpush

@endsection
