@extends('layouts.public')

@section('title', __('site.account_my_favorites'))

@section('content')
@php
    $hasAny = $favorites->total() > 0 || $savedServices->isNotEmpty() || $savedOffers->isNotEmpty();
@endphp
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">@lang('site.account_my_favorites')</h1>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">@include('public.account._nav')</div>

        <div class="lg:col-span-3 space-y-10">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(! $hasAny)
                <div class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <x-icon name="heart" class="w-16 h-16 mx-auto mb-3 text-gray-300" />
                    <p class="text-gray-500 mb-4">@lang('site.account_no_favorites')</p>
                    <a href="{{ route('search') }}" class="bg-sage-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors inline-block">
                        @lang('site.account_start_browsing')
                    </a>
                </div>
            @endif

            {{-- Saved clinics --}}
            @if($favorites->total() > 0)
                <section>
                    <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.account_fav_clinics')</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        @foreach($favorites as $clinic)
                            <div class="relative">
                                @include('public.partials.clinic-card', ['clinic' => $clinic])
                                <form method="POST" action="{{ route('favorites.toggle', $clinic->slug) }}"
                                      class="absolute top-3 end-3 z-20">
                                    @csrf
                                    <button type="submit"
                                            title="{{ __('site.favorite_remove') }}"
                                            class="bg-white/95 hover:bg-red-50 text-red-500 w-11 h-11 rounded-full flex items-center justify-center shadow transition-colors">
                                        <x-icon name="heart-solid" class="w-4 h-4" />
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                    @if($favorites->hasPages())
                        <div class="mt-6">{{ $favorites->links() }}</div>
                    @endif
                </section>
            @endif

            {{-- Saved services --}}
            @if($savedServices->isNotEmpty())
                <section>
                    <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.account_fav_services')</h2>
                    <div class="space-y-3">
                        @foreach($savedServices as $service)
                            @php $gone = method_exists($service, 'trashed') && $service->trashed(); @endphp
                            <div class="bg-white rounded-xl shadow-sm p-4 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-semibold text-gray-800">{{ $service->name }}</p>
                                        @if($gone)
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">@lang('site.saved_badge_deleted')</span>
                                        @endif
                                    </div>
                                    @if($service->clinic)
                                        <p class="text-xs text-gray-500 mt-1">{{ $service->clinic->name }}</p>
                                    @endif
                                    @if($service->price)
                                        <p class="text-sage-700 font-bold text-sm mt-1">{{ number_format($service->price) }} <span class="text-xs font-normal"><x-riyal /></span></p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    @if(! $gone && $service->clinic)
                                        <a href="{{ route('clinic.show', $service->clinic->slug) }}"
                                           class="text-xs font-semibold text-sage-600 hover:text-sage-700 whitespace-nowrap">@lang('site.saved_view')</a>
                                    @endif
                                    <x-save-button :model="$service" type="service" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Saved offers --}}
            @if($savedOffers->isNotEmpty())
                <section>
                    <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.account_fav_offers')</h2>
                    <div class="space-y-3">
                        @foreach($savedOffers as $offer)
                            @php
                                $gone    = method_exists($offer, 'trashed') && $offer->trashed();
                                $expired = ! $gone && $offer->ends_at && $offer->ends_at->isPast();
                            @endphp
                            <div class="bg-white rounded-xl shadow-sm p-4 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-semibold text-gray-800">{{ $offer->title }}</p>
                                        @if($gone)
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">@lang('site.saved_badge_deleted')</span>
                                        @elseif($expired)
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">@lang('site.saved_badge_expired')</span>
                                        @endif
                                    </div>
                                    @if($offer->clinic)
                                        <p class="text-xs text-gray-500 mt-1">{{ $offer->clinic->name }}</p>
                                    @endif
                                    @if($offer->price !== null)
                                        <p class="text-sage-700 font-bold text-sm mt-1">{{ number_format((float) $offer->price) }} <span class="text-xs font-normal"><x-riyal /></span></p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    @if(! $gone && ! $expired && $offer->clinic)
                                        {{-- Live offers open their detail page; otherwise fall back to the clinic. --}}
                                        <a href="{{ $offer->status() === 'active'
                                                ? route('offer.show', ['slug' => $offer->clinic->slug, 'offer' => $offer->id])
                                                : route('clinic.show', $offer->clinic->slug) }}"
                                           class="text-xs font-semibold text-sage-600 hover:text-sage-700 whitespace-nowrap">@lang('site.saved_view')</a>
                                    @endif
                                    <x-save-button :model="$offer" type="offer" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
