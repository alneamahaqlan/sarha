@extends('layouts.public')

@section('title', $clinic->name)
@section('description', Str::limit($clinic->description ?? '', 160))

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
    ])->filter(fn($v) => $v !== null)->toArray();
@endphp
@push('head')
<script type="application/ld+json">{!! json_encode($schemaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-teal-600">@lang('site.breadcrumb_home')</a>
        <span class="mx-2">/</span>
        <a href="{{ route('search') }}" class="hover:text-teal-600">@lang('site.breadcrumb_clinics')</a>
        <span class="mx-2">/</span>
        <span class="text-gray-800">{{ $clinic->name }}</span>
    </nav>

    {{-- Hero Card --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        @if($clinic->logo)
            <div class="h-56 bg-gray-100">
                <img src="{{ Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}" class="w-full h-full object-cover">
            </div>
        @endif
        <div class="p-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $clinic->name }}</h1>
                        @if($clinic->is_featured)
                            <span class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full text-xs font-semibold">⭐ @lang('site.featured')</span>
                        @endif
                        @if($clinic->isPremium())
                            <span class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full text-xs font-semibold">@lang('site.premium_badge')</span>
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
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $clinic->city->display_name ?? '' }}
                            @if($clinic->address) — {{ $clinic->address }} @endif
                        </span>
                    </div>

                    @if($clinic->categories->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mt-3">
                            @foreach($clinic->categories as $cat)
                                <span class="bg-teal-50 text-teal-700 px-3 py-1 rounded-full text-sm">
                                    {{ $cat->emoji ?? '' }} {{ $cat->display_name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Share + Book CTA --}}
                <div class="flex flex-col gap-3 items-end">
                    @include('public.partials.share-buttons')
                    <a href="{{ route('clinic.book.form', $clinic->slug) }}"
                       class="bg-teal-600 hover:bg-teal-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors whitespace-nowrap">
                        @lang('site.book_appointment')
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div x-data="{ tab: 'services' }" class="mb-8">
        <div class="border-b border-gray-200 mb-6 overflow-x-auto">
            <div class="flex gap-1 min-w-max">
                @foreach([
                    'services' => ['site.tab_services', $clinic->services->count()],
                    'reviews'  => ['site.tab_reviews', $clinic->google_reviews_count ?? 0],
                    'articles' => ['site.tab_articles', $clinic->articles->count()],
                    'about'    => ['site.tab_about', null],
                ] as $key => [$labelKey, $count])
                    <button @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-teal-600 text-teal-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                            class="px-4 py-3 font-semibold border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap">
                        @lang($labelKey)
                        @if(! is_null($count))
                            <span :class="tab === '{{ $key }}' ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-600'"
                                  class="text-xs px-2 py-0.5 rounded-full">{{ $count }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">

                {{-- Services tab --}}
                <div x-show="tab === 'services'" x-cloak class="space-y-6">
                    @include('public.partials.sub-clinics')

                    @if($clinic->services->isNotEmpty())
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.services_and_prices')</h2>
                            <div class="divide-y divide-gray-100">
                                @foreach($clinic->services as $service)
                                    <div class="py-3 flex items-center justify-between gap-4">
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-gray-800">{{ $service->name }}</p>
                                            @if($service->description)
                                                <p class="text-sm text-gray-500 mt-0.5">{{ $service->description }}</p>
                                            @endif
                                            @if($service->hasActiveOffer())
                                                <span class="inline-block mt-1 bg-red-50 text-red-700 text-xs px-2 py-0.5 rounded-full font-semibold">
                                                    -{{ $service->discountPercentage() }}%
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-end flex-shrink-0">
                                            @if($service->price)
                                                <span class="text-teal-700 font-bold">
                                                    @if($service->old_price)
                                                        <span class="line-through text-gray-400 text-sm me-1">{{ number_format($service->old_price) }}</span>
                                                    @endif
                                                    {{ number_format($service->price) }}
                                                    <span class="text-xs font-normal">@lang('site.currency_sar')</span>
                                                </span>
                                                <a href="{{ route('clinic.book.form', ['slug' => $clinic->slug, 'service' => $service->id]) }}"
                                                   class="block mt-1 text-xs text-teal-600 hover:underline">
                                                    @lang('site.book_appointment')
                                                </a>
                                            @else
                                                <span class="text-gray-400 text-sm">@lang('site.call_for_inquiry')</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
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
                                    <div class="border border-gray-100 rounded-lg p-4 hover:border-teal-200 transition-colors">
                                        <h3 class="font-semibold text-gray-800 mb-1">{{ $article->title }}</h3>
                                        <p class="text-sm text-gray-500">{{ Str::limit($article->excerpt ?? strip_tags($article->body ?? ''), 160) }}</p>
                                    </div>
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
                               class="block mt-3 text-center bg-teal-50 text-teal-700 hover:bg-teal-100 py-2 rounded-lg text-sm font-semibold transition-colors">
                                🗺 @lang('site.directions_open_maps')
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
                            <a href="tel:{{ $clinic->phone }}" class="flex items-center gap-3 text-gray-700 hover:text-teal-600">
                                <span class="bg-teal-50 p-2 rounded-lg">📞</span>
                                <span dir="ltr">{{ $clinic->phone }}</span>
                            </a>
                        @endif
                        @if($clinic->email)
                            <div class="flex items-center gap-3 text-gray-700">
                                <span class="bg-teal-50 p-2 rounded-lg">✉️</span>
                                <span class="text-sm" dir="ltr">{{ $clinic->email }}</span>
                            </div>
                        @endif
                        @if($clinic->instagram)
                            <a href="https://instagram.com/{{ $clinic->instagram }}" target="_blank" rel="noopener" class="flex items-center gap-3 text-gray-700 hover:text-pink-600">
                                <span class="bg-pink-50 p-2 rounded-lg">📸</span>
                                <span class="text-sm" dir="ltr">@{{ $clinic->instagram }}</span>
                            </a>
                        @endif
                        @if($clinic->twitter)
                            <div class="flex items-center gap-3 text-gray-700">
                                <span class="bg-blue-50 p-2 rounded-lg">🐦</span>
                                <span class="text-sm" dir="ltr">{{ $clinic->twitter }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Custom price quote --}}
                <div class="bg-white rounded-xl shadow-sm p-6" x-data="{ open: false }">
                    <h3 class="font-bold text-gray-800 mb-2">@lang('site.request_price_quote')</h3>
                    <p class="text-sm text-gray-500 mb-4">@lang('site.price_quote_subtitle')</p>
                    <button type="button" @click="open = true"
                            class="w-full bg-amber-500 hover:bg-amber-600 text-white py-2.5 rounded-lg font-semibold transition-colors">
                        @lang('site.request_price_quote')
                    </button>

                    {{-- Modal --}}
                    <div x-show="open" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="open = false">
                        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-gray-800">@lang('site.request_price_quote')</h3>
                                <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-700">✕</button>
                            </div>
                            <form method="POST" action="{{ route('clinic.quote', $clinic->slug) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.full_name')</label>
                                    <input type="text" name="customer_name" required value="{{ auth('web')->user()?->name }}"
                                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.phone_number')</label>
                                    <input type="tel" name="customer_phone" required pattern="05[0-9]{8}" value="{{ auth('web')->user()?->phone }}"
                                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400" dir="ltr">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.service_short_name')</label>
                                    <input type="text" name="service_name" required maxlength="255"
                                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.service_description')</label>
                                    <textarea name="description" rows="3" required minlength="10" maxlength="2000"
                                              class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400"></textarea>
                                </div>
                                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white py-2.5 rounded-lg font-semibold transition-colors">
                                    @lang('site.submit_quote_request')
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- Similar --}}
    @include('public.partials.similar-clinics')
</div>

{{-- Floating buttons --}}
@include('public.partials.floating-actions')
@endsection
