@extends('layouts.public')

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
@endphp
@push('head')
<script type="application/ld+json">{!! json_encode($schemaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
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

    {{-- Hero Card --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        @if($clinic->logo)
            <div class="h-56 bg-gray-100">
                <img src="{{ Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}" loading="lazy" class="w-full h-full object-cover">
            </div>
        @endif
        <div class="p-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $clinic->name }}</h1>
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

                    @if($clinic->categories->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mt-3">
                            @foreach($clinic->categories as $cat)
                                <span class="inline-flex items-center gap-1.5 bg-sage-50 text-sage-700 px-3 py-1 rounded-full text-sm">
                                    <x-category-icon :emoji="$cat->emoji" class="w-4 h-4" /> {{ $cat->display_name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Share + Favorite + Book CTA --}}
                <div class="flex flex-col gap-3 items-end">
                    <div class="flex items-center gap-2">
                        @auth('web')
                            @php $isFavorited = auth('web')->user()->hasFavorited($clinic); @endphp
                            <form method="POST" action="{{ route('favorites.toggle', $clinic->slug) }}">
                                @csrf
                                <button type="submit"
                                        title="{{ $isFavorited ? __('site.favorite_remove') : __('site.favorite_add') }}"
                                        class="w-9 h-9 rounded-full {{ $isFavorited ? 'bg-red-50 text-red-500 hover:bg-red-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }} flex items-center justify-center transition-colors">
                                    <x-icon :name="$isFavorited ? 'heart-solid' : 'heart'" class="w-4 h-4" />
                                </button>
                            </form>
                        @endauth
                        @include('public.partials.share-buttons')
                    </div>
                    <a href="{{ route('clinic.book.form', $clinic->slug) }}"
                       data-track="booking" data-clinic="{{ $clinic->id }}"
                       class="bg-sage-600 hover:bg-sage-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors whitespace-nowrap">
                        @lang('site.book_appointment')
                    </a>
                </div>
            </div>

            {{-- Unified action bar: Call · WhatsApp · Directions --}}
            <div class="mt-5 pt-5 border-t border-gray-100">
                @include('public.partials.action-bar')
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    @php
        $featuredOffers = $clinic->services->where('is_featured_offer', true);
        $offersCount = $featuredOffers->count() + $clinic->packages->count();
        // Deep link from the homepage offers card: ?service=<id> opens the
        // services tab pre-filtered to that one service. Any other entry
        // point lands on offers (the conversion-rich tab) by default.
        $focusedServiceId = (int) request('service') ?: null;
        $initialTab = $focusedServiceId ? 'services' : 'offers';
    @endphp
    <div x-data="{ tab: '{{ $initialTab }}' }" class="mb-8">
        <div class="border-b border-gray-200 mb-6 overflow-x-auto">
            <div class="flex gap-1 min-w-max">
                @foreach([
                    'offers'   => ['site.tab_offers', $offersCount],
                    'services' => ['site.tab_services', $clinic->services->count()],
                    'clinics'  => ['site.tab_clinics', $clinic->subClinics->count()],
                    'doctors'  => ['site.tab_doctors', $clinic->doctors->count()],
                    'before_after' => ['site.tab_before_after', $clinic->beforeAfterPhotos->count()],
                    'reviews'  => ['site.tab_reviews', $clinic->google_reviews_count ?? 0],
                    'articles' => ['site.tab_articles', $clinic->articles->count()],
                    'about'    => ['site.tab_about', null],
                ] as $key => [$labelKey, $count])
                    <button @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-sage-600 text-sage-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                            class="px-4 py-3 font-semibold border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap">
                        @lang($labelKey)
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

                {{-- Offers & packages tab — first by default (highest conversion intent). --}}
                <div x-show="tab === 'offers'" x-cloak class="space-y-6">
                    @include('public.partials.offers', ['featuredOffers' => $featuredOffers])
                </div>

                {{-- Services tab — price-list view: services grouped by
                     sub-clinic. Filters down to one service when ?service=
                     is set so a customer who clicked a specific service
                     card sees only that one (with a "view all" pill). --}}
                <div x-show="tab === 'services'" x-cloak class="space-y-6">
                    @include('public.partials.sub-clinics', ['focusedServiceId' => $focusedServiceId])
                </div>

                {{-- Clinics tab — structural directory: each sub-clinic as
                     a navigational card (specialty + description + counts),
                     without the nested service rows. --}}
                <div x-show="tab === 'clinics'" x-cloak class="space-y-6">
                    @include('public.partials.clinics')
                </div>

                {{-- Doctors tab --}}
                <div x-show="tab === 'doctors'" x-cloak class="space-y-6">
                    @include('public.partials.doctors')
                </div>

                {{-- Before / after tab --}}
                <div x-show="tab === 'before_after'" x-cloak class="space-y-6">
                    @include('public.partials.before-after')
                </div>

                {{-- Reviews tab --}}
                <div x-show="tab === 'reviews'" x-cloak>
                    @include('public.partials.google-reviews')
                </div>

                {{-- Articles tab --}}
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

                {{-- About tab --}}
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
                               class="mt-3 inline-flex w-full items-center justify-center gap-2 text-center bg-sage-50 text-sage-700 hover:bg-sage-100 py-2 rounded-lg text-sm font-semibold transition-colors">
                                <x-icon name="map" class="w-4 h-4" /> @lang('site.directions_open_maps')
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Sidebar (always visible) --}}
            <aside class="lg:col-span-1 space-y-5">
                {{-- Working hours --}}
                @include('public.partials.working-hours')

                {{-- Contact info --}}
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="font-bold text-gray-800 mb-4">@lang('site.contact_info')</h3>
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

                {{-- Social media bar --}}
                @include('public.partials.social-bar')

                {{-- Custom price quote — broadcast to all complexes in the chosen cities --}}
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="font-bold text-gray-800 mb-2">@lang('site.request_price_quote')</h3>
                    <p class="text-sm text-gray-500 mb-4">@lang('site.quote_request_subtitle')</p>
                    <a href="{{ route('quotes.request') }}"
                       class="block text-center w-full bg-gradient-to-l from-gold-primary to-gold-deep hover:from-gold-deep hover:to-gold-deep text-white py-2.5 rounded-lg font-semibold transition-colors">
                        @lang('site.request_price_quote')
                    </a>
                </div>
            </aside>
        </div>
    </div>

    {{-- Similar --}}
    @include('public.partials.similar-clinics')
</div>

{{-- Floating buttons (desktop) + sticky action bar (mobile) --}}
@include('public.partials.floating-actions')
@include('public.partials.mobile-action-bar')
@endsection
