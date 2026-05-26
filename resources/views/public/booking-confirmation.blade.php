@extends('layouts.public')

@section('title', __('site.booking_confirmed_title'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-16">

    <div class="bg-white rounded-2xl shadow-sm p-10 text-center">
        <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6 text-4xl">
            ✓
        </div>

        <h1 class="text-3xl font-bold text-gray-900 mb-2">@lang('site.booking_confirmed_title')</h1>
        <p class="text-gray-500 mb-8">@lang('site.booking_confirmed_subtitle')</p>

        <div class="bg-sage-50 border-2 border-sage-200 rounded-xl p-5 mb-8">
            <p class="text-xs text-sage-700 uppercase tracking-wider mb-1">@lang('site.booking_reference_label')</p>
            <p class="text-2xl font-bold text-sage-900 font-mono tracking-wider" dir="ltr">
                {{ $booking->reference_code }}
            </p>
        </div>

        <div class="space-y-3 text-start text-sm bg-gray-50 rounded-xl p-5 mb-8">
            <div class="flex justify-between">
                <span class="text-gray-500">@lang('site.booking_target_clinic')</span>
                <span class="font-semibold text-gray-800">{{ $booking->clinic->name }}</span>
            </div>
            @if($booking->service)
                <div class="flex justify-between">
                    <span class="text-gray-500">@lang('site.booking_target_service')</span>
                    <span class="font-medium text-gray-800">{{ $booking->service->name }}</span>
                </div>
            @endif
            <div class="flex justify-between">
                <span class="text-gray-500">@lang('site.phone_number')</span>
                <span class="font-medium text-gray-800" dir="ltr">{{ $booking->customer_phone }}</span>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('home') }}" class="bg-sage-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-sage-700 transition-colors">
                @lang('site.booking_back_home')
            </a>
            <a href="{{ route('search') }}" class="bg-white border border-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                @lang('site.booking_browse_more')
            </a>
        </div>
    </div>
</div>
@endsection
