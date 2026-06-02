@extends('layouts.public')

@section('title', $service->name . ' — ' . $clinic->name)
@section('description', Str::limit($service->description ?? $clinic->name, 160))
@section('og_image', $service->image ? Storage::url($service->image) : asset('images/og-default.png'))

@php
    $imageUrl = $service->image ? Storage::url($service->image) : null;
    $emoji    = $service->categories->first()?->emoji;
    $bookHref = route('clinic.book.form', ['slug' => $clinic->slug, 'service' => $service->id]);
@endphp

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6 flex items-center gap-2 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span>/</span>
        <a href="{{ route('clinic.show', $clinic->slug) }}" class="hover:text-sage-600">{{ $clinic->name }}</a>
        <span>/</span>
        <span class="text-gray-700">@lang('site.detail_service_title')</span>
    </nav>

    <div class="grid md:grid-cols-2 gap-8 items-start">
        {{-- Image --}}
        <div class="relative aspect-[4/3] rounded-2xl overflow-hidden bg-gradient-to-br from-sage-mist to-gold-whisper flex items-center justify-center text-5xl">
            @if($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $service->name }}" class="absolute inset-0 w-full h-full object-cover">
            @elseif($emoji)
                <span aria-hidden="true">{{ $emoji }}</span>
            @else
                <x-icon name="star" class="w-12 h-12 text-sage-400" />
            @endif
        </div>

        {{-- Details --}}
        <div>
            <a href="{{ route('clinic.show', $clinic->slug) }}" class="text-sm text-sage-600 hover:underline">{{ $clinic->name }}@if($clinic->city) · {{ $clinic->city->display_name }}@endif</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $service->name }}</h1>

            {{-- Category chips + sub-clinic --}}
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($service->categories as $cat)
                    <span class="inline-flex items-center gap-1 text-xs bg-sage-50 text-sage-700 px-2.5 py-1 rounded-full">
                        @if($cat->emoji)<span aria-hidden="true">{{ $cat->emoji }}</span>@endif {{ $cat->name }}
                    </span>
                @endforeach
                @if($service->subClinic)
                    <a href="{{ route('subclinic.show', ['slug' => $clinic->slug, 'subClinic' => $service->subClinic->id]) }}"
                       class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-600 hover:text-sage-700 px-2.5 py-1 rounded-full">
                        <x-icon name="building" class="w-3 h-3" /> {{ $service->subClinic->display_name }}
                    </a>
                @endif
            </div>

            @if($service->price)
                <div class="mt-4 flex items-baseline gap-2">
                    @if($service->price_from)
                        <span class="text-sm font-normal text-gray-500">@lang('site.price_from')</span>
                    @endif
                    <span class="text-sage-700 font-bold text-3xl">{{ number_format((float) $service->price) }}
                        <span class="text-sm font-normal">@lang('site.currency_sar')</span>
                    </span>
                </div>
            @endif

            @if($service->description)
                <p class="text-gray-600 leading-relaxed mt-4 whitespace-pre-line">{{ $service->description }}</p>
            @endif

            @if($service->price_includes)
                <p class="text-sm text-emerald-700 mt-3">
                    <span class="font-semibold">@lang('site.price_includes'):</span> {{ $service->price_includes }}
                </p>
            @endif
            @if($service->price_excludes)
                <p class="text-sm text-gray-500 mt-1">
                    <span class="font-semibold">@lang('site.price_excludes'):</span> {{ $service->price_excludes }}
                </p>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <a href="{{ $bookHref }}"
                   data-track="booking" data-clinic="{{ $clinic->id }}"
                   class="inline-flex items-center justify-center gap-2 min-h-touch bg-sage-600 hover:bg-sage-700 text-white font-semibold px-6 py-3 rounded-lg shadow-sm transition-colors">
                    <x-icon name="calendar" class="w-5 h-5" />
                    @lang('site.book_appointment')
                </a>
                @include('public.partials.detail-nav-buttons', ['clinic' => $clinic])
            </div>
        </div>
    </div>

    {{-- Similar services from other complexes — clicking goes to the service page. --}}
    @include('public.partials.similar-cards', [
        'items' => $similarServices,
        'type' => 'service',
        'title' => __('site.similar_services_title'),
        'subtitle' => __('site.similar_services_subtitle'),
    ])
</div>
@endsection
