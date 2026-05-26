@extends('layouts.public')

@section('title', __('site.account_my_quotes'))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="bg-gradient-to-l from-sage-600 to-sage-700 rounded-xl p-6 text-white mb-6">
        <h1 class="text-2xl font-bold mb-2">{{ $user->name ?: __('site.account_my_quotes') }}</h1>
        <p class="text-sage-100" dir="ltr">{{ $user->phone }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">@include('public.account._nav')</div>

        <div class="lg:col-span-3 space-y-3">
            @forelse($quotes as $quote)
                <a href="{{ route('quotes.show', $quote->id) }}" class="block bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-bold text-gray-800">{{ $quote->service_name }}</h2>
                            <p class="text-sm text-gray-500 mt-1">{{ Str::limit($quote->description, 140) }}</p>
                        </div>
                        <span class="bg-sage-50 text-sage-700 text-xs px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0">
                            {{ __('site.quote_replies_count', ['count' => $quote->replies_count]) }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        @foreach($quote->cities as $city)
                            <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">
                                <x-icon name="map-pin" class="w-3 h-3" /> {{ $city->display_name }}
                            </span>
                        @endforeach
                        <span class="text-xs text-gray-400 ms-auto">{{ $quote->created_at->diffForHumans() }}</span>
                    </div>
                </a>
            @empty
                <div class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <x-icon name="clipboard" class="w-16 h-16 mx-auto mb-3 text-gray-300" />
                    <p class="text-gray-500 mb-4">@lang('site.account_no_quotes')</p>
                    <a href="{{ route('quotes.request') }}" class="bg-sage-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors inline-block">
                        @lang('site.quote_request_cta')
                    </a>
                </div>
            @endforelse

            @if($quotes->hasPages())
                <div class="mt-4">{{ $quotes->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
