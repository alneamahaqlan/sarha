@extends('layouts.public')

@section('title', $quoteRequest->service_name)

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span class="mx-2">/</span>
        <a href="{{ route('quotes.board') }}" class="hover:text-sage-600">@lang('site.quotes_board_title')</a>
        <span class="mx-2">/</span>
        <span class="text-gray-800">{{ Str::limit($quoteRequest->service_name, 40) }}</span>
    </nav>

    {{-- Request --}}
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <div class="flex items-start justify-between gap-3 mb-2">
            <h1 class="text-xl font-bold text-gray-900">{{ $quoteRequest->service_name }}</h1>
            @if($isOwner)
                <span class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full whitespace-nowrap">@lang('site.quote_your_request')</span>
            @endif
        </div>
        <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $quoteRequest->description }}</p>
        <div class="flex flex-wrap items-center gap-2 mt-4">
            @foreach($quoteRequest->cities as $city)
                <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">
                    <x-icon name="map-pin" class="w-3 h-3" /> {{ $city->display_name }}
                </span>
            @endforeach
            <span class="text-xs text-gray-400 ms-auto">{{ $quoteRequest->created_at->diffForHumans() }}</span>
        </div>
    </div>

    {{-- Replies --}}
    <h2 class="text-lg font-bold text-gray-800 mb-3">
        {{ __('site.quote_replies_count', ['count' => $replies->count()]) }}
    </h2>

    @if($replies->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">
            @lang('site.quote_no_replies')
        </div>
    @else
        <div class="space-y-3">
            @foreach($replies as $reply)
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <a href="{{ route('clinic.show', $reply->clinic->slug) }}" class="font-bold text-gray-800 hover:text-sage-600">
                            {{ $reply->clinic->name }}
                        </a>
                        <div class="flex items-center gap-2">
                            @if($reply->price)
                                <span class="text-sage-700 font-bold">{{ number_format($reply->price) }} <span class="text-xs font-normal">@lang('site.currency_sar')</span></span>
                            @endif
                            @if($isOwner && ! $reply->is_public)
                                <span class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded-full">@lang('site.quote_reply_private')</span>
                            @endif
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $reply->body }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
