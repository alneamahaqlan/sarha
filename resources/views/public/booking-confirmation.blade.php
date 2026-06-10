@extends('layouts.public')

@push('scripts')
<script>
@if(!empty($amPhoneHash) && !empty($amMetaId))
// Advanced matching (opted-in): attach hashed phone before the conversion.
// fbq exists only after consent → no-op otherwise.
if (window.fbq) fbq('init', @json($amMetaId), { ph: @json($amPhoneHash) });
@endif
window.sarhaTrack && window.sarhaTrack('submit_booking', { clinic_id: {{ (int) $booking->clinic_id }}, booking_ref: @json($booking->reference_code) });
</script>
@endpush

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

        <div class="flex flex-col sm:flex-row flex-wrap gap-3 justify-center">
            <a href="{{ route('home') }}" class="bg-sage-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-sage-700 transition-colors">
                @lang('site.booking_back_home')
            </a>
            <a href="{{ route('clinic.show', $booking->clinic->slug) }}" class="bg-white border border-sage-200 text-sage-700 px-6 py-3 rounded-lg font-semibold hover:bg-sage-50 transition-colors">
                @lang('site.booking_continue_clinic')
            </a>
            <a href="{{ route('search') }}" class="bg-white border border-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                @lang('site.booking_browse_more')
            </a>
            <button type="button" onclick="history.back()" class="bg-white border border-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                @lang('site.booking_back')
            </button>
        </div>
    </div>
</div>

{{-- Post-booking "register now" prompt — only for guests who booked from a
     landing page and have no account yet. The standard OTP login links the
     account to the unverified customer record by phone (no duplicate). --}}
@if(session('lp_register_prompt') && ! auth('web')->check())
    <div x-data="{ open: true }" x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,.5)">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-7 text-center" @click.outside="open = false">
            <div class="w-14 h-14 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">✓</div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">@lang('site.lp_register_title')</h2>
            <p class="text-gray-600 mb-6">{{ __('site.lp_register_body', ['phone' => session('lp_register_prompt')]) }}</p>
            <div class="flex flex-col gap-2">
                <a href="{{ route('login', ['redirect' => route('home')]) }}"
                   class="bg-sage-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-sage-700 transition">
                    @lang('site.lp_register_cta')
                </a>
                <button type="button" @click="open = false" class="text-gray-500 px-6 py-2 rounded-lg font-medium hover:text-gray-700 transition">
                    @lang('site.lp_register_dismiss')
                </button>
            </div>
        </div>
    </div>
@endif
@endsection
