@extends('layouts.public')

@section('title', ($photo->title ?: __('site.detail_before_after_title')) . ' — ' . $clinic->name)
@section('og_image', $photo->after_url ?: asset('images/og-default.png'))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6 flex items-center gap-2 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span>/</span>
        <a href="{{ route('clinic.show', $clinic->slug) }}" class="hover:text-sage-600">{{ $clinic->name }}</a>
        <span>/</span>
        <span class="text-gray-700">@lang('site.detail_before_after_title')</span>
    </nav>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 overflow-hidden">
        @if($photo->display_mode === 'slider' && $photo->before_url && $photo->after_url)
            {{-- Interactive drag-to-compare slider (touch + RTL aware). --}}
            @include('public.partials.before-after-slider', [
                'before'      => $photo->before_url,
                'after'       => $photo->after_url,
                'heightClass' => 'h-72 sm:h-96 w-full',
            ])
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2">
                <div class="relative">
                    @if($photo->before_url)
                        <img src="{{ $photo->before_url }}" alt="@lang('site.before')" class="w-full h-72 sm:h-96 object-cover">
                    @endif
                    <span class="absolute top-3 start-3 bg-black/60 text-white text-xs px-2.5 py-1 rounded">@lang('site.before')</span>
                </div>
                <div class="relative">
                    @if($photo->after_url)
                        <img src="{{ $photo->after_url }}" alt="@lang('site.after')" class="w-full h-72 sm:h-96 object-cover">
                    @endif
                    <span class="absolute top-3 start-3 bg-sage-600/85 text-white text-xs px-2.5 py-1 rounded">@lang('site.after')</span>
                </div>
            </div>
        @endif

        <div class="p-6">
            <a href="{{ route('clinic.show', $clinic->slug) }}" class="text-sm text-sage-600 hover:underline">{{ $clinic->name }}@if($clinic->city) · {{ $clinic->city->display_name }}@endif</a>
            @if($photo->title)
                <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $photo->title }}</h1>
            @else
                <h1 class="text-2xl font-bold text-gray-800 mt-1">@lang('site.detail_before_after_title')</h1>
            @endif

            <div class="mt-3 flex flex-wrap gap-2">
                @if($photo->subClinic)
                    <a href="{{ route('subclinic.show', ['slug' => $clinic->slug, 'subClinic' => $photo->subClinic->id]) }}"
                       class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-600 hover:text-sage-700 px-2.5 py-1 rounded-full">
                        <x-icon name="building" class="w-3 h-3" /> {{ $photo->subClinic->display_name }}
                    </a>
                @endif
                @if($photo->service)
                    <a href="{{ route('service.show', ['slug' => $clinic->slug, 'service' => $photo->service->id]) }}"
                       class="inline-flex items-center gap-1 text-xs bg-sage-50 text-sage-700 hover:bg-sage-100 px-2.5 py-1 rounded-full">
                        {{ $photo->service->name }}
                    </a>
                @endif
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if($photo->service)
                    <a href="{{ route('clinic.book.form', ['slug' => $clinic->slug, 'service' => $photo->service->id]) }}"
                       data-track="booking" data-clinic="{{ $clinic->id }}"
                       class="inline-flex items-center justify-center gap-2 min-h-touch bg-sage-600 hover:bg-sage-700 text-white font-semibold px-6 py-3 rounded-lg shadow-sm transition-colors">
                        <x-icon name="calendar" class="w-5 h-5" />
                        @lang('site.book_appointment')
                    </a>
                @endif
                @include('public.partials.detail-nav-buttons', ['clinic' => $clinic])
            </div>
        </div>
    </div>
</div>
@endsection
