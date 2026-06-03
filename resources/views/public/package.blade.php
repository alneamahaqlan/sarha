@extends('layouts.public')

@section('title', $package->name . ' — ' . $clinic->name)
@section('description', Str::limit($package->description ?? $clinic->name, 160))

@php
    $bookHref = route('clinic.book.form', $clinic->slug);
    $discount = $package->discountPercentage();
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6 flex items-center gap-2 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span>/</span>
        <a href="{{ route('clinic.show', $clinic->slug) }}" class="hover:text-sage-600">{{ $clinic->name }}</a>
        <span>/</span>
        <span class="text-gray-700">@lang('site.detail_package_title')</span>
    </nav>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-sage-100 p-6">
        <a href="{{ route('clinic.show', $clinic->slug) }}" class="text-sm text-sage-600 hover:underline">{{ $clinic->name }}@if($clinic->city) · {{ $clinic->city->display_name }}@endif</a>
        <div class="flex items-start justify-between gap-3 mt-1">
            <h1 class="text-2xl font-bold text-gray-800">{{ $package->name }}</h1>
            @if($discount)
                <span class="bg-red-50 text-red-700 text-sm px-2.5 py-1 rounded-full font-semibold whitespace-nowrap">-{{ $discount }}%</span>
            @endif
        </div>

        @if($package->description)
            <p class="text-gray-600 leading-relaxed mt-3 whitespace-pre-line">{{ $package->description }}</p>
        @endif

        @if($package->services->isNotEmpty())
            <div class="mt-5">
                <h2 class="text-sm font-bold text-gray-700 mb-2">@lang('site.package_includes')</h2>
                <ul class="space-y-1.5">
                    @foreach($package->services as $svc)
                        <li class="flex items-start gap-2 text-sm text-gray-700">
                            <x-icon name="check-circle" class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                            <span>
                                <a href="{{ route('service.show', ['slug' => $clinic->slug, 'service' => $svc->id]) }}" class="hover:text-sage-700">{{ $svc->name }}</a>
                                @if($svc->pivot->note)
                                    <span class="block text-xs text-sage-600">{{ $svc->pivot->note }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-6 flex items-end gap-3">
            @if($package->old_price)
                <span class="text-base text-gray-400 line-through">{{ number_format((float) $package->old_price) }}</span>
            @endif
            <span class="text-sage-700 font-bold text-3xl">{{ number_format((float) $package->price) }}
                <span class="text-sm font-normal">@lang('site.currency_sar')</span>
            </span>
        </div>

        @if($package->expires_at)
            <p class="mt-2 text-xs text-amber-600">
                <x-icon name="clock" class="w-3.5 h-3.5 inline-block" />
                {{ __('site.offer_until', ['date' => $package->expires_at->translatedFormat('d M Y')]) }}
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
@endsection
