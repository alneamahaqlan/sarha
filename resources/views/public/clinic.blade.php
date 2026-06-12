@extends('layouts.public')

@push('head')
<style>
    /* Collapsible specialties — hide the native disclosure triangle and
       give the expanded list a smooth, modern reveal. */
    .cats-details > summary { list-style: none; }
    .cats-details > summary::-webkit-details-marker { display: none; }
    .cats-details[open] .cat-chevron { transform: rotate(180deg); }
    .cats-details[open] .cats-reveal { animation: catsReveal .28s ease-out; }
    @keyframes catsReveal {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .cats-details[open] .cats-reveal { animation: none; }
    }
</style>
@endpush

@section('title', $clinic->name)
@section('description', Str::limit($clinic->description ?? '', 160))
@section('og_type', 'business.business')
@section('og_title', $clinic->name)
@section('og_description', Str::limit($clinic->description ?? '', 160))
@section('og_image', $clinic->logo ? Storage::url($clinic->logo) : asset('images/og-default.png'))

@php
    $schemaPayload = collect([
        '@context'    => 'https://schema.org',
        '@type'       => 'MedicalBusiness',
        'name'        => $clinic->name,
        'description' => Str::limit($clinic->description ?? '', 200),
        'telephone'   => $clinic->phone,
        'url'         => url()->current(),
        'address'     => $clinic->city ? [
            '@type'           => 'PostalAddress',
            'addressLocality' => $clinic->city->display_name,
            'addressCountry'  => 'SA',
        ] : null,
        'geo' => ($clinic->latitude && $clinic->longitude) ? [
            '@type'     => 'GeoCoordinates',
            'latitude'  => $clinic->latitude,
            'longitude' => $clinic->longitude,
        ] : null,
        'aggregateRating' => ($clinic->google_reviews_count ?? 0) > 0 ? [
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format($clinic->google_reviews_avg_rating, 1),
            'reviewCount' => $clinic->google_reviews_count,
        ] : null,
        'hasMap'  => $clinic->directionsUrl(),
        'sameAs'  => array_values($clinic->socialLinks()) ?: null,
    ])->filter(fn($v) => $v !== null)->toArray();

    /*
     * Page Builder integration.
     * `$pageSections` is a Collection keyed by section `key`, containing
     *   only ACTIVE sections in the admin's chosen order.
     * Use `$pageSections->has('key')` to gate visibility, and the
     *   sort_order on each row to drive tab/sidebar ordering.
     * `$builder` (ClinicPageBuilderService) resolves the localized
     *   display title per section, honoring admin overrides.
     */
    $pageSections = $pageSections ?? collect();
    $builder      = $builder      ?? app(\App\Services\ClinicPageBuilderService::class);

    // Body-content sections that render inside the tab panel, sorted by
    // the admin's chosen order. Hero / contact_info / floating_ctas /
    // working_hours / social_links / similar_services live outside the
    // tab system so they're not in this list.
    //
    // NB: filter() not only() — Eloquent Collection's only() looks up by
    // primary key, not by the keyBy('key') label.
    $tabSectionKeys = [
        'offers', 'packages', 'services', 'sub_clinics', 'doctors',
        'before_after', 'google_reviews', 'articles', 'about',
    ];
    $activeTabs = $pageSections
        ->filter(fn ($s) => in_array($s->key, $tabSectionKeys, true))
        ->sortBy('sort_order')
        ->values();

    // Sidebar widgets, in admin-chosen order. Always-on widgets (contact,
    // request-quote) anchor the sidebar; clinic admin can hide
    // working_hours + social_links and reorder them.
    $sidebarSectionKeys = ['working_hours', 'contact_info', 'social_links'];
    $activeSidebar = $pageSections
        ->filter(fn ($s) => in_array($s->key, $sidebarSectionKeys, true))
        ->sortBy('sort_order')
        ->values();

    $firstTabKey = optional($activeTabs->first())->key ?? 'about';
@endphp
@push('head')
<script type="application/ld+json">{!! json_encode($schemaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@push('scripts')
<script>window.sarhaTrack && window.sarhaTrack('view_clinic', { clinic_id: {{ (int) $clinic->id }}, clinic_slug: @json($clinic->slug), city: @json(optional($clinic->city)->name) });</script>
<script>
    // Specialty filter store — clicking a category chip in the hero narrows
    // every content section (offers, services, sub-clinics, doctors, packages)
    // to items tagged with that specialty. `active` is the selected category
    // id (or null = show all). Each filterable card calls show(itsCatIds).
    document.addEventListener('alpine:init', () => {
        Alpine.store('spec', {
            active: null,
            names: {},                          // id -> display name (for the banner)
            toggle(id) { this.active = this.active === id ? null : id; },
            clear() { this.active = null; },
            isActive(id) { return this.active === id; },
            // A card shows when no filter is active, or its category list
            // includes the active specialty. Cards with no specialty fall out
            // while a filter is active (they aren't tied to this specialty).
            show(cats) { return !this.active || (cats || []).includes(this.active); },
            get activeName() { return this.active ? (this.names[this.active] || '') : ''; },
        });
    });

    // Instagram-style story viewer: full-screen overlay with segmented
    // progress bars, auto-advance, and tap-to-navigate.
    function storyViewer(items) {
        return {
            items: items || [],
            open: false,
            index: 0,
            progress: 0,
            duration: 5000,   // ms per story
            _timer: null,
            _seen: new Set(),   // story ids already counted this page load
            show(i = 0) {
                if (!this.items.length) return;
                this.index = i;
                this.open = true;
                document.body.style.overflow = 'hidden';
                this.track();
                this.play();
            },
            // Count a view once per story per page load (fire-and-forget).
            track() {
                const item = this.items[this.index];
                if (!item || this._seen.has(item.id)) return;
                this._seen.add(item.id);
                try {
                    const fd = new FormData();
                    fd.append('story', item.id);
                    navigator.sendBeacon(@json(route('track.story')), fd);
                } catch (e) { /* tracking must never break the viewer */ }
            },
            play() {
                this.progress = 0;
                clearInterval(this._timer);
                const step = 50;
                this._timer = setInterval(() => {
                    this.progress += (step / this.duration) * 100;
                    if (this.progress >= 100) this.next();
                }, step);
            },
            next() {
                if (this.index < this.items.length - 1) { this.index++; this.track(); this.play(); }
                else { this.close(); }
            },
            prev() {
                if (this.index > 0) { this.index--; this.track(); }
                this.play();
            },
            close() {
                this.open = false;
                clearInterval(this._timer);
                document.body.style.overflow = '';
            },
        };
    }
</script>
@endpush

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span class="mx-2">/</span>
        <a href="{{ route('search') }}" class="hover:text-sage-600">@lang('site.breadcrumb_clinics')</a>
        <span class="mx-2">/</span>
        <span class="text-gray-800">{{ $clinic->name }}</span>
    </nav>

    {{-- Hero Card (protected — always renders) --}}
    @if($pageSections->has('hero'))
    @php
        $storyItems = $clinic->stories->map(fn ($s) => [
            'id'      => $s->id,
            'url'     => $s->image ? Storage::url($s->image) : null,
            'caption' => $s->caption,
        ])->filter(fn ($s) => $s['url'])->values();
        $hasStories = $storyItems->isNotEmpty();
    @endphp
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6" x-data="storyViewer(@js($storyItems))">
        <div class="p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="flex items-start gap-4 w-full sm:flex-1 min-w-0">
                    {{-- Instagram-style circular logo avatar (uploaded from the
                         clinic profile → "logo"). Falls back to the clinic's
                         first letter so the header never looks empty. When the
                         clinic has live stories, the avatar gets the gradient
                         "story ring" and opens the story viewer on tap. --}}
                    @if($hasStories)
                        <button type="button" @click="show(0)" title="@lang('site.stories_view')"
                                class="flex-shrink-0 rounded-full p-[3px] bg-gradient-to-tr from-amber-400 via-pink-500 to-purple-600">
                            <span class="block rounded-full p-[2px] bg-white">
                                @if($clinic->logo)
                                    <img src="{{ Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}"
                                         fetchpriority="high" decoding="async"
                                         class="block w-20 h-20 sm:w-24 sm:h-24 rounded-full object-cover">
                                @else
                                    <span class="block w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-sage-50 text-sage-600 flex items-center justify-center text-3xl font-bold">{{ mb_substr($clinic->name, 0, 1) }}</span>
                                @endif
                            </span>
                        </button>
                    @elseif($clinic->logo)
                        <img src="{{ Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}"
                             fetchpriority="high" decoding="async"
                             class="w-20 h-20 sm:w-24 sm:h-24 rounded-full object-cover ring-4 ring-white shadow-md flex-shrink-0">
                    @else
                        <span class="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-sage-50 text-sage-600 flex items-center justify-center text-3xl font-bold ring-4 ring-white shadow-md flex-shrink-0">
                            {{ mb_substr($clinic->name, 0, 1) }}
                        </span>
                    @endif
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $clinic->name }}</h1>
                        {{-- Verified badge — package-driven, set in ClinicController::show via FeatureGate. --}}
                        @if($clinic->is_verified_badge ?? false)
                            <span class="inline-flex items-center gap-1 bg-sage-mist text-sage-deep px-2 py-0.5 rounded-full text-xs font-semibold" title="@lang('site.verified_badge_tooltip')">
                                <x-icon name="check-circle" class="w-3.5 h-3.5" /> @lang('site.verified_badge')
                            </span>
                        @endif
                        @if($clinic->is_featured)
                            <span class="inline-flex items-center gap-1 bg-gold-whisper text-gold-deep px-2 py-0.5 rounded-full text-xs font-semibold"><x-icon name="star-solid" class="w-3.5 h-3.5" /> @lang('site.featured')</span>
                        @endif
                        @if($clinic->isPremium())
                            <span class="inline-flex items-center gap-1 bg-gradient-to-l from-gold-primary to-gold-deep text-white px-2.5 py-0.5 rounded-full text-xs font-semibold"><x-icon name="star-solid" class="w-3.5 h-3.5" /> @lang('site.premium_badge')</span>
                        @endif
                        @if($clinic->workingHours->isNotEmpty())
                            @if($clinic->isOpenNow())
                                <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 px-2 py-0.5 rounded-full text-xs font-semibold">● @lang('site.working_hours_open_now')</span>
                            @else
                                <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs font-semibold">○ @lang('site.working_hours_closed')</span>
                            @endif
                        @endif
                        @include('public.partials.impressions-badge', ['clinic' => $clinic])
                    </div>

                    {{-- Rating + Location --}}
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                        @if(($clinic->google_reviews_count ?? 0) > 0)
                            <span class="flex items-center gap-1">
                                <span class="text-yellow-500">★</span>
                                <span class="font-semibold text-gray-800">{{ number_format($clinic->google_reviews_avg_rating, 1) }}</span>
                                <span>({{ __('site.reviews_count_label', ['count' => $clinic->google_reviews_count]) }})</span>
                            </span>
                        @endif
                        @php $heroDirections = $clinic->directionsUrl(); @endphp
                        <a @if($heroDirections) href="{{ $heroDirections }}" target="_blank" rel="noopener" data-track="directions" data-clinic="{{ $clinic->id }}" @endif
                           class="flex items-center gap-1 {{ $heroDirections ? 'hover:text-sage-600 transition-colors' : 'pointer-events-none' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $clinic->city->display_name ?? '' }}
                            @if($clinic->address) — {{ $clinic->address }} @endif
                        </a>
                    </div>

                    {{-- Instagram-style public tallies: followers · customers ·
                         bookings. Followers always shows; customers/bookings
                         stay hidden until they clear PUBLIC_STAT_MIN_DISPLAY so a
                         brand-new complex doesn't broadcast "0 عميل / 0 حجز" to
                         visitors and competitors during the launch stage. --}}
                    @php $statMin = \App\Models\Clinic::PUBLIC_STAT_MIN_DISPLAY; @endphp
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mt-4">
                        <div class="flex items-center gap-1.5">
                            <x-icon name="users" class="w-4 h-4 text-sage-600" />
                            <span class="font-bold text-gray-900">{{ number_format($clinic->followers_count) }}</span>
                            <span class="text-sm text-gray-500">@lang('site.followers')</span>
                        </div>
                        @if($clinic->customers_count >= $statMin)
                            <div class="flex items-center gap-1.5">
                                <x-icon name="user" class="w-4 h-4 text-sage-600" />
                                <span class="font-bold text-gray-900">{{ number_format($clinic->customers_count) }}</span>
                                <span class="text-sm text-gray-500">@lang('site.customers')</span>
                            </div>
                        @endif
                        @if($clinic->bookings_count >= $statMin)
                            <div class="flex items-center gap-1.5">
                                <x-icon name="calendar" class="w-4 h-4 text-sage-600" />
                                <span class="font-bold text-gray-900">{{ number_format($clinic->bookings_count) }}</span>
                                <span class="text-sm text-gray-500">@lang('site.bookings_made')</span>
                            </div>
                        @endif
                    </div>

                    {{-- Specialties — collapse into a single elegant chip and
                         expand on click (smooth height reveal via the
                         <details> element + the .cats-reveal animation). --}}
                    @if($clinic->categories->isNotEmpty())
                        {{-- Clickable specialty filter: tapping a chip narrows
                             every content section below to that specialty.
                             Tap again (or "الكل") to clear. --}}
                        <div x-data x-init="$store.spec.names = @js($clinic->categories->pluck('display_name', 'id'))"
                             class="flex flex-wrap items-center gap-2 mt-3">
                            <button type="button" @click="$store.spec.clear()"
                                    :class="!$store.spec.active ? 'bg-sage-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium transition-colors">
                                @lang('site.clinic_spec_filter_all')
                            </button>
                            @foreach($clinic->categories as $cat)
                                <button type="button" @click="$store.spec.toggle({{ $cat->id }})"
                                        :class="$store.spec.isActive({{ $cat->id }}) ? 'bg-sage-600 text-white ring-2 ring-sage-400' : 'bg-sage-50 text-sage-700 hover:bg-sage-100'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm transition-colors">
                                    <x-category-icon :emoji="$cat->emoji" :icon="$cat->icon" class="w-4 h-4" /> {{ $cat->display_name }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                </div>{{-- /avatar + name flex wrapper --}}

                {{-- Follow + Share + Favorite + Book CTA --}}
                <div class="flex flex-col gap-3 items-end">
                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        {{-- Follow (Instagram-style). Route is public: a guest is
                             routed through OTP login and the follow is applied on
                             return. Outline until following, filled once active. --}}
                        @php $isFollowing = auth('web')->check() && auth('web')->user()->isFollowing($clinic); @endphp
                        <form method="POST" action="{{ route('clinic.follow.toggle', $clinic->slug) }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 min-h-touch px-4 py-2 rounded-full text-sm font-semibold transition-colors {{ $isFollowing ? 'bg-sage-600 text-white hover:bg-sage-700' : 'bg-white text-sage-700 ring-1 ring-sage-300 hover:bg-sage-50' }}">
                                <x-icon :name="$isFollowing ? 'check-circle' : 'user-plus'" class="w-4 h-4" />
                                {{ $isFollowing ? __('site.following') : __('site.follow') }}
                            </button>
                        </form>
                        @auth('web')
                            @php $isFavorited = auth('web')->user()->hasFavorited($clinic); @endphp
                            <form method="POST" action="{{ route('favorites.toggle', $clinic->slug) }}" class="js-save-form">
                                @csrf
                                <button type="submit"
                                        data-fav-toggle
                                        data-saved="{{ $isFavorited ? '1' : '0' }}"
                                        data-class-on="bg-red-50 text-red-500 hover:bg-red-100"
                                        data-class-off="bg-gray-100 text-gray-500 hover:bg-gray-200"
                                        data-title-on="{{ __('site.favorite_remove') }}"
                                        data-title-off="{{ __('site.favorite_add') }}"
                                        title="{{ $isFavorited ? __('site.favorite_remove') : __('site.favorite_add') }}"
                                        class="w-11 h-11 rounded-full {{ $isFavorited ? 'bg-red-50 text-red-500 hover:bg-red-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }} flex items-center justify-center transition-colors">
                                    <span data-fav-icon-on class="{{ $isFavorited ? '' : 'hidden' }}"><x-icon name="heart-solid" class="w-4 h-4" /></span>
                                    <span data-fav-icon-off class="{{ $isFavorited ? 'hidden' : '' }}"><x-icon name="heart" class="w-4 h-4" /></span>
                                </button>
                            </form>
                        @endauth
                        @include('public.partials.social-bar', ['variant' => 'inline'])
                    </div>
                    <a href="{{ route('clinic.book.form', $clinic->slug) }}"
                       data-track="booking" data-clinic="{{ $clinic->id }}"
                       class="inline-flex items-center justify-center min-h-touch bg-sage-600 hover:bg-sage-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors whitespace-nowrap">
                        @lang('site.book_appointment')
                    </a>
                </div>
            </div>

            {{-- Unified action bar: Call · WhatsApp · Directions --}}
            <div class="mt-5 pt-5 border-t border-gray-100">
                @include('public.partials.action-bar')
            </div>
        </div>

        {{-- Instagram-style full-screen story viewer (Alpine scope = this hero
             card via storyViewer()). Renders only when there are stories. --}}
        @if($hasStories)
            @include('public.partials.story-viewer', ['clinic' => $clinic])
        @endif
    </div>
    @endif

    {{-- Tabs — only renders if at least one tab section is active. --}}
    @if($activeTabs->isNotEmpty())
    @php
        // Active offers for THIS clinic — only running ones (within
        // [starts_at, ends_at] + is_active). Featured first.
        $activeOffers = $clinic->offers
            ->where('is_active', true)
            ->filter(fn ($o) => $o->starts_at <= now() && $o->ends_at >= now())
            ->sortByDesc('is_featured')
            ->values();

        $tabCounts = [
            'offers'         => $activeOffers->count(),
            'packages'       => $clinic->packages->count(),
            'services'       => $clinic->services->count(),
            'sub_clinics'    => $clinic->subClinics->count(),
            'doctors'        => $clinic->doctors->count(),
            'before_after'   => $clinic->beforeAfterPhotos->count(),
            'google_reviews' => $clinic->google_reviews_count ?? 0,
            'articles'       => $clinic->articles->count(),
            'about'          => null,
        ];

        // Flat search index for the in-complex search box — services,
        // doctors, offers, packages. Filtered client-side by Alpine. Each
        // row: type, typeLabel, name, sub(caption), url (null → switch tab).
        $subNames = $clinic->subClinics->keyBy('id');
        $searchItems = [];
        foreach ($activeOffers as $o) {
            $searchItems[] = [
                'type' => 'offer', 'typeLabel' => __('site.search_type_offer'),
                'name' => $o->title, 'sub' => $o->service?->name,
                'url' => route('offer.show', ['slug' => $clinic->slug, 'offer' => $o->id]),
            ];
        }
        foreach ($clinic->packages as $p) {
            $searchItems[] = [
                'type' => 'package', 'typeLabel' => __('site.search_type_package'),
                'name' => $p->name, 'sub' => null, 'url' => null, 'tab' => 'packages',
            ];
        }
        foreach ($clinic->services as $svc) {
            $searchItems[] = [
                'type' => 'service', 'typeLabel' => __('site.search_type_service'),
                'name' => $svc->name,
                'sub'  => $svc->sub_clinic_id ? optional($subNames->get($svc->sub_clinic_id))->display_name : null,
                'url'  => route('service.show', ['slug' => $clinic->slug, 'service' => $svc->id]),
            ];
        }
        foreach ($clinic->doctors as $d) {
            $searchItems[] = [
                'type' => 'doctor', 'typeLabel' => __('site.search_type_doctor'),
                'name' => $d->name, 'sub' => $d->specialty,
                'url' => route('doctor.show', ['slug' => $clinic->slug, 'doctor' => $d->id]),
            ];
        }
    @endphp
    <div x-data="{
            tab: '{{ $firstTabKey }}',
            q: '',
            items: @js($searchItems),
            results() {
                const t = this.q.trim().toLowerCase();
                if (!t) return [];
                return this.items.filter(i =>
                    (i.name || '').toLowerCase().includes(t) ||
                    (i.sub || '').toLowerCase().includes(t)
                ).slice(0, 50);
            }
         }" class="mb-8">

        {{-- Active specialty filter banner — shows which specialty the hero
             chips narrowed the page to, with a one-tap clear. --}}
        <div x-show="$store.spec.active" x-cloak
             class="mb-4 flex items-center justify-between gap-3 bg-sage-50 border border-sage-100 rounded-xl px-4 py-3">
            <span class="text-sm text-sage-800">
                @lang('site.clinic_spec_filter_active') <b x-text="$store.spec.activeName"></b>
            </span>
            <button type="button" @click="$store.spec.clear()"
                    class="text-sm font-semibold text-sage-700 hover:text-sage-800 inline-flex items-center gap-1 whitespace-nowrap">
                <x-icon name="x" class="w-4 h-4" /> @lang('site.clinic_spec_filter_clear')
            </button>
        </div>

        {{-- In-complex search: filters services / doctors / offers / packages
             instantly (client-side). Empty query → normal tabs. --}}
        <div class="relative mb-5">
            <span class="absolute inset-y-0 start-3 flex items-center text-gray-400">
                <x-icon name="search" class="w-5 h-5" />
            </span>
            <input type="search" x-model="q"
                   placeholder="@lang('site.clinic_search_placeholder')"
                   class="w-full ps-11 pe-4 py-3 rounded-xl border border-gray-200 focus:border-sage-400 focus:ring-2 focus:ring-sage-100 outline-none text-sm">
        </div>

        {{-- Search results (only while typing). --}}
        <div x-show="q.trim()" x-cloak class="mb-6">
            <template x-if="results().length === 0">
                <p class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">@lang('site.clinic_search_no_results')</p>
            </template>
            <div class="space-y-2" x-show="results().length > 0">
                <template x-for="(item, idx) in results()" :key="item.type + '-' + idx">
                    <a :href="item.url || '#'"
                       @click="if (!item.url) { $event.preventDefault(); tab = item.tab; q = ''; }"
                       class="flex items-center justify-between gap-3 bg-white rounded-xl shadow-sm ring-1 ring-gray-100 hover:shadow-md hover:ring-sage-200 transition-all p-4">
                        <span class="min-w-0">
                            <span class="font-semibold text-gray-800 line-clamp-1" x-text="item.name"></span>
                            <span class="text-xs text-gray-500 line-clamp-1" x-show="item.sub" x-text="item.sub"></span>
                        </span>
                        <span class="shrink-0 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-sage-50 text-sage-700" x-text="item.typeLabel"></span>
                    </a>
                </template>
            </div>
        </div>

        <div x-show="!q.trim()">
        <div class="border-b border-gray-200 mb-6 overflow-x-auto">
            <div class="flex gap-1 min-w-max">
                @foreach($activeTabs as $tabSection)
                    @php
                        $key   = $tabSection->key;
                        $label = $builder->titleFor($tabSection);
                        $count = $tabCounts[$key] ?? null;
                    @endphp
                    <button @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-sage-600 text-sage-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                            class="px-4 py-3 font-semibold border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap">
                        {{ $label }}
                        @if(! is_null($count))
                            <span :class="tab === '{{ $key }}' ? 'bg-sage-100 text-sage-700' : 'bg-gray-100 text-gray-600'"
                                  class="text-xs px-2 py-0.5 rounded-full">{{ $count }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">

                @if($pageSections->has('offers'))
                <div x-show="tab === 'offers'" x-cloak class="space-y-6">
                    @include('public.partials.offers', ['activeOffers' => $activeOffers, 'spec' => true])
                </div>
                @endif

                @if($pageSections->has('packages'))
                <div x-show="tab === 'packages'" x-cloak class="space-y-6">
                    @include('public.partials.packages', ['spec' => true])
                </div>
                @endif

                @if($pageSections->has('services'))
                <div x-show="tab === 'services'" x-cloak class="space-y-6">
                    @include('public.partials.sub-clinics', ['spec' => true])
                </div>
                @endif

                @if($pageSections->has('sub_clinics'))
                <div x-show="tab === 'sub_clinics'" x-cloak class="space-y-6">
                    @include('public.partials.clinics', ['spec' => true])
                </div>
                @endif

                @if($pageSections->has('doctors'))
                <div x-show="tab === 'doctors'" x-cloak class="space-y-6">
                    @include('public.partials.doctors', ['spec' => true])
                </div>
                @endif

                @if($pageSections->has('before_after'))
                <div x-show="tab === 'before_after'" x-cloak class="space-y-6">
                    @include('public.partials.before-after')
                </div>
                @endif

                @if($pageSections->has('google_reviews'))
                <div x-show="tab === 'google_reviews'" x-cloak>
                    @include('public.partials.google-reviews')
                </div>
                @endif

                @if($pageSections->has('articles'))
                <div x-show="tab === 'articles'" x-cloak>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.clinic_articles')</h2>
                        @if($clinic->articles->isEmpty())
                            <p class="text-center text-gray-400 py-8">@lang('site.no_articles_yet')</p>
                        @else
                            <div class="space-y-3">
                                @foreach($clinic->articles as $article)
                                    <a href="{{ route('article.show', $article->slug) }}"
                                       class="block border border-gray-100 rounded-lg p-4 hover:border-sage-200 hover:shadow-sm transition-all group">
                                        <h3 class="font-semibold text-gray-800 mb-1 group-hover:text-sage-600 transition-colors">{{ $article->title }}</h3>
                                        <p class="text-sm text-gray-500">{{ Str::limit($article->meta_description ?? strip_tags($article->body ?? ''), 160) }}</p>
                                        <span class="inline-block mt-2 text-xs text-sage-600 font-medium">@lang('site.read_more') →</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                @endif

                @if($pageSections->has('about'))
                <div x-show="tab === 'about'" x-cloak class="space-y-6">
                    @if($clinic->description)
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h2 class="text-lg font-bold text-gray-800 mb-3">@lang('site.about_clinic')</h2>
                            <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $clinic->description }}</p>
                        </div>
                    @endif

                    @if($clinic->latitude && $clinic->longitude)
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h2 class="text-lg font-bold text-gray-800 mb-3">@lang('site.contact_info')</h2>
                            <iframe class="w-full h-64 rounded-lg border border-gray-100"
                                    src="https://www.google.com/maps?q={{ $clinic->latitude }},{{ $clinic->longitude }}&hl={{ app()->getLocale() }}&z=15&output=embed"
                                    loading="lazy"></iframe>
                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $clinic->latitude }},{{ $clinic->longitude }}"
                               target="_blank" rel="noopener"
                               class="mt-3 inline-flex w-full items-center justify-center gap-2 text-center min-h-touch bg-sage-50 text-sage-700 hover:bg-sage-100 py-2 px-4 rounded-lg text-sm font-semibold transition-colors">
                                <x-icon name="map" class="w-4 h-4" /> @lang('site.directions_open_maps')
                            </a>
                        </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- Sidebar — only the active widgets, in admin-chosen order.
                 "Request price quote" is always-on (no toggle): it's a
                 platform-level CTA, not a clinic-controlled section. --}}
            <aside class="lg:col-span-1 space-y-5">
                @foreach($activeSidebar as $sidebarSection)
                    @php $sidebarTitleOverride = $sidebarSection->title_ar || $sidebarSection->title_en ? $builder->titleFor($sidebarSection) : null; @endphp
                    @switch($sidebarSection->key)
                        @case('working_hours')
                            @include('public.partials.working-hours', ['sidebarTitleOverride' => $sidebarTitleOverride])
                            @break

                        @case('contact_info')
                            <div class="bg-white rounded-xl shadow-sm p-6">
                                <h3 class="font-bold text-gray-800 mb-4">{{ $builder->titleFor($sidebarSection) }}</h3>
                                <div class="space-y-3">
                                    @if($clinic->phone)
                                        <a href="tel:{{ $clinic->phone }}" data-track="call" data-clinic="{{ $clinic->id }}" class="flex items-center gap-3 text-gray-700 hover:text-sage-600">
                                            <span class="bg-sage-50 text-sage-600 p-2 rounded-lg"><x-icon name="phone" class="w-4 h-4" /></span>
                                            <span dir="ltr">{{ $clinic->phone }}</span>
                                        </a>
                                    @endif
                                    @if($clinic->email)
                                        <div class="flex items-center gap-3 text-gray-700">
                                            <span class="bg-sage-50 text-sage-600 p-2 rounded-lg"><x-icon name="envelope" class="w-4 h-4" /></span>
                                            <span class="text-sm" dir="ltr">{{ $clinic->email }}</span>
                                        </div>
                                    @endif
                                    @if($address = $clinic->address)
                                        <div class="flex items-start gap-3 text-gray-700">
                                            <span class="bg-sage-50 text-sage-600 p-2 rounded-lg"><x-icon name="map-pin" class="w-4 h-4" /></span>
                                            <span class="text-sm">{{ $clinic->city->display_name ?? '' }}@if($clinic->city) — @endif{{ $address }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @break

                        @case('social_links')
                            {{-- Sharing lives here now (swapped with the hero's
                                 social icons): a sidebar card of share targets. --}}
                            <div class="bg-white rounded-xl shadow-sm p-6">
                                <h3 class="font-bold text-gray-800 mb-4">{{ $sidebarTitleOverride ?? __('site.share') }}</h3>
                                @include('public.partials.share-buttons', ['variant' => 'bare'])
                            </div>
                            @break
                    @endswitch
                @endforeach
            </aside>
        </div>
        </div>{{-- /x-show !q (tabs hidden while searching) --}}
    </div>
    @endif

    {{-- Frequently asked questions — clinic-authored Q&A. Sits just above
         "similar complexes"; managed via the Page Builder (تخصيص صفحتي). --}}
    @if($pageSections->has('faqs'))
        @include('public.partials.faqs', ['faqsSection' => $pageSections->get('faqs'), 'builder' => $builder])
    @endif

    {{-- One "similar" section only: the complex page (and other non-detail
         pages) shows similar COMPLEXES. Service/offer/sub-clinic/doctor detail
         pages each show their own matching type. Gated + sized by the
         super-admin's similarity settings. --}}
    @include('public.partials.similar-cards', [
        'items' => $similarClinics ?? collect(),
        'type' => 'clinic',
        'title' => __('site.similar_clinics'),
        'subtitle' => null,
    ])

    {{-- Complaint (to this clinic) / platform-report call-to-action --}}
    @include('public.partials.feedback-cta', ['clinic' => $clinic])
</div>

{{-- Floating buttons (desktop) + sticky action bar (mobile) — protected
     (always renders). The toggle stays in the page builder for layout
     parity but is locked active. --}}
@if($pageSections->has('floating_ctas'))
    @include('public.partials.floating-actions')
    @include('public.partials.mobile-action-bar')
@endif
@endsection
