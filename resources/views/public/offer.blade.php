@extends('layouts.public')

@push('scripts')
<script>window.sarhaTrack && window.sarhaTrack('view_offer', { clinic_id: {{ (int) $clinic->id }}, offer_id: {{ (int) $offer->id }} });</script>
@endpush

@section('title', $offer->title)
@section('description', Str::limit($offer->description ?? $clinic->name, 160))
@section('og_type', 'product')
@section('og_title', $offer->title)
@section('og_image', $offer->effectiveImage() ? Storage::url($offer->effectiveImage()) : asset('images/og-default.png'))

@php
    $imageUrl = $offer->effectiveImage() ? Storage::url($offer->effectiveImage()) : null;
    $discount = $offer->discountPercentage();
    $isServiceLinked = $offer->type === \App\Models\Offer::TYPE_SERVICE && $offer->service;
    // Every offer now opens the booking form. A service-linked offer pre-selects
    // its service; a general offer lets the customer pick one (or "خدمات أخرى").
    // The offer id is carried so the form can show a banner + stamp the notes.
    $ctaHref = route('clinic.book.form', array_filter([
        'slug'    => $clinic->slug,
        'service' => $isServiceLinked ? $offer->service_id : null,
        'offer'   => $offer->id,
    ]));
@endphp

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6 flex items-center gap-2 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span>/</span>
        <a href="{{ route('clinic.show', $clinic->slug) }}" class="hover:text-sage-600">{{ $clinic->name }}</a>
        <span>/</span>
        <span class="text-gray-700">@lang('site.detail_offer_title')</span>
    </nav>

    <div class="grid md:grid-cols-2 gap-8 items-start">
        {{-- Image --}}
        <div class="relative aspect-[4/3] rounded-2xl overflow-hidden bg-gradient-to-br from-sage-mist to-gold-whisper flex items-center justify-center">
            @if($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $offer->title }}" class="absolute inset-0 w-full h-full object-cover">
            @else
                <x-icon name="star" class="w-12 h-12 text-sage-400" />
            @endif
            @if($discount)
                <span class="absolute top-4 start-4 bg-red-500 text-white text-sm font-bold px-3 py-1 rounded-full shadow-md">-{{ $discount }}%</span>
            @endif
        </div>

        {{-- Details --}}
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('clinic.show', $clinic->slug) }}" class="text-sm text-sage-600 hover:underline">{{ $clinic->name }}@if($clinic->city) · {{ $clinic->city->display_name }}@endif</a>
                @include('public.partials.impressions-badge', ['clinic' => $clinic])
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $offer->title }}</h1>

            @if($isServiceLinked)
                <p class="text-sm text-gray-500 mt-2">@lang('site.offer_on_service'): {{ $offer->service->name }}</p>
            @endif

            @if($offer->price !== null)
                <div class="mt-4 flex items-baseline gap-3">
                    <span class="text-sage-700 font-bold text-3xl">{{ number_format((float) $offer->price) }}
                        <span class="text-sm font-normal"><x-riyal /></span>
                    </span>
                    @if($offer->old_price !== null)
                        <span class="text-lg text-gray-400 line-through">{{ number_format((float) $offer->old_price) }}</span>
                    @endif
                </div>
            @endif

            @if($offer->description)
                <p class="text-gray-600 leading-relaxed mt-4 whitespace-pre-line">{{ $offer->description }}</p>
            @endif

            <p class="text-xs text-gray-400 mt-4">
                <x-icon name="clock" class="w-3.5 h-3.5 inline-block" />
                @lang('site.offer_valid_until', ['date' => $offer->ends_at->translatedFormat('j F Y')])
            </p>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <a href="{{ $ctaHref }}"
                   data-track="booking" data-clinic="{{ $clinic->id }}"
                   class="inline-flex items-center justify-center gap-2 min-h-touch bg-sage-600 hover:bg-sage-700 text-white font-semibold px-6 py-3 rounded-lg shadow-sm transition-colors">
                    <x-icon name="calendar" class="w-5 h-5" />
                    {{ __('site.book_now_label') }}
                </a>
                <x-add-to-cart-button :model="$offer" type="offer" :clinic="$clinic" />
                @include('public.partials.detail-nav-buttons', ['clinic' => $clinic])
            </div>
        </div>
    </div>

    {{-- Similar offers from other complexes — clicking goes to the offer page. --}}
    @include('public.partials.similar-cards', [
        'items' => $similarOffers,
        'type' => 'offer',
        'title' => __('site.similar_offers'),
        'subtitle' => __('site.similar_from_other_complexes'),
    ])
</div>
@endsection
