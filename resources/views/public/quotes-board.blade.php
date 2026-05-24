@extends('layouts.public')

@section('title', __('site.quotes_board_title'))
@section('description', __('site.quotes_board_subtitle'))

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">@lang('site.quotes_board_title')</h1>
            <p class="text-sm text-gray-500 mt-1">@lang('site.quotes_board_subtitle')</p>
        </div>
        <a href="{{ route('quotes.request') }}"
           class="bg-sage-600 hover:bg-sage-700 text-white px-5 py-2.5 rounded-lg font-semibold transition-colors whitespace-nowrap">
            @lang('site.quote_request_cta')
        </a>
    </div>

    @if($requests->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-12 text-center text-gray-400">
            @lang('site.quotes_board_empty')
        </div>
    @else
        <div class="space-y-4">
            @foreach($requests as $req)
                <a href="{{ route('quotes.show', $req->id) }}" class="block bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-bold text-gray-800">{{ $req->service_name }}</h2>
                            <p class="text-sm text-gray-500 mt-1">{{ Str::limit($req->description, 160) }}</p>
                        </div>
                        <span class="bg-sage-50 text-sage-700 text-xs px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0">
                            {{ __('site.quote_replies_count', ['count' => $req->public_replies_count]) }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        @foreach($req->cities as $city)
                            <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">
                                <x-icon name="map-pin" class="w-3 h-3" /> {{ $city->display_name }}
                            </span>
                        @endforeach
                        <span class="text-xs text-gray-400 ms-auto">{{ $req->created_at->diffForHumans() }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        @if($requests->hasPages())
            <div class="mt-6">{{ $requests->links() }}</div>
        @endif
    @endif
</div>
@endsection
