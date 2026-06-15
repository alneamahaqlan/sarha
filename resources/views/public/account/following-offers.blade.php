@extends('layouts.public')

@section('title', __('site.followed_offers_page_title'))

@section('content')
@php $hasFilters = $q !== '' || $catSlug || $clinicId; @endphp
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">@lang('site.followed_offers_page_title')</h1>
        <p class="text-sm text-gray-500 mt-1">@lang('site.followed_offers_page_subtitle')</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">@include('public.account._nav')</div>

        <div class="lg:col-span-3 space-y-6">
            {{-- Search + filters (GET so it's shareable / paginates cleanly) --}}
            <form method="GET" action="{{ route('account.following.offers') }}"
                  class="bg-white rounded-2xl shadow-sm p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="sm:col-span-2 lg:col-span-2">
                    <div class="relative">
                        <x-icon name="search" class="w-4 h-4 text-gray-400 absolute top-1/2 -translate-y-1/2 start-3" />
                        <input type="search" name="q" value="{{ $q }}"
                               placeholder="@lang('site.followed_offers_search_ph')"
                               class="w-full ps-9 pe-3 py-2.5 rounded-lg border border-gray-200 focus:border-sage-500 focus:ring-1 focus:ring-sage-500 text-sm">
                    </div>
                </div>
                @if($categories->isNotEmpty())
                    <select name="category" class="py-2.5 px-3 rounded-lg border border-gray-200 focus:border-sage-500 focus:ring-1 focus:ring-sage-500 text-sm bg-white">
                        <option value="">@lang('site.followed_filter_category')</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->slug }}" @selected($catSlug === $cat->slug)>{{ $cat->display_name }}</option>
                        @endforeach
                    </select>
                @endif
                @if($clinics->isNotEmpty())
                    <select name="clinic" class="py-2.5 px-3 rounded-lg border border-gray-200 focus:border-sage-500 focus:ring-1 focus:ring-sage-500 text-sm bg-white">
                        <option value="">@lang('site.followed_filter_clinic')</option>
                        @foreach($clinics as $c)
                            <option value="{{ $c->id }}" @selected((string) $clinicId === (string) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                @endif
                <div class="sm:col-span-2 lg:col-span-4 flex items-center gap-2">
                    <button type="submit" class="bg-sage-600 hover:bg-sage-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                        @lang('site.followed_filter_apply')
                    </button>
                    @if($hasFilters)
                        <a href="{{ route('account.following.offers') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">
                            @lang('site.followed_filter_reset')
                        </a>
                    @endif
                </div>
            </form>

            @if($offers->total() === 0)
                <div class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <x-icon name="sparkles" class="w-16 h-16 mx-auto mb-3 text-gray-300" />
                    <p class="text-gray-500 mb-4">
                        {{ $hasFilters ? __('site.followed_offers_empty_filtered') : __('site.followed_offers_empty') }}
                    </p>
                    <a href="{{ route('account.following') }}" class="bg-sage-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors inline-block">
                        @lang('site.followed_clinics_page_title')
                    </a>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 gap-3 sm:gap-4 items-stretch">
                    @foreach($offers as $offer)
                        @include('public.partials.offer-card', ['offer' => $offer, 'clinic' => $offer->clinic, 'large' => false])
                    @endforeach
                </div>
                @if($offers->hasPages())
                    <div class="mt-6">{{ $offers->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
