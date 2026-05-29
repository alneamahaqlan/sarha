@extends('layouts.public')

@section('title', __('site.booking_page_title', ['clinic' => $clinic->name]))

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span class="mx-2">/</span>
        <a href="{{ route('clinic.show', $clinic->slug) }}" class="hover:text-sage-600">{{ $clinic->name }}</a>
        <span class="mx-2">/</span>
        <span class="text-gray-800">@lang('site.book_appointment')</span>
    </nav>

    {{-- Pre-filled service banner — appears when the customer arrived from
         an offer card on the homepage. Confirms exactly which service the
         booking is for; the select below stays editable so they can change. --}}
    @if(! empty($service))
        <div class="bg-sage-50 border border-sage-200 rounded-xl p-4 mb-4 flex items-start gap-3">
            <span class="shrink-0 mt-0.5 inline-flex items-center justify-center w-8 h-8 rounded-full bg-sage-600 text-white">
                <x-icon name="check-circle" class="w-5 h-5" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-sage-800">@lang('site.booking_selected_service_title')</p>
                <p class="text-sm text-sage-900 mt-0.5">
                    {{ $service->name }}
                    @if($service->price)
                        — <span class="font-semibold">{{ number_format($service->price) }} <span class="text-xs font-normal">@lang('site.currency_sar')</span></span>
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- Notice --}}
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6 text-sm">
        @lang('site.booking_page_notice')
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Form --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">
                    {{ __('site.booking_page_title', ['clinic' => $clinic->name]) }}
                </h1>

                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 text-sm mb-5">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @if(session('otp_required'))
                    {{-- One-time verification step (first booking → registers the customer). --}}
                    <div class="bg-sage-50 border border-sage-200 text-sage-900 rounded-lg p-4 text-sm mb-5 space-y-1">
                        <p class="font-semibold">@lang('site.otp_step_title')</p>
                        <p>@lang('site.otp_step_intro')</p>
                        <p class="text-xs text-sage-700">{{ __('site.otp_sent_to', ['phone' => session('otp_phone')]) }}</p>
                        @if(session('dev_code'))
                            <p class="text-xs font-mono bg-white inline-block px-2 py-1 rounded border border-sage-200">DEV: {{ session('dev_code') }}</p>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('clinic.book.verify', $clinic->slug) }}" class="space-y-5">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.otp_code_label') <span class="text-red-500">*</span></label>
                            <input type="text" name="code" inputmode="numeric" maxlength="6" required autofocus
                                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-center tracking-[0.5em] text-lg focus:outline-none focus:ring-2 focus:ring-sage-400" dir="ltr">
                        </div>
                        <p class="text-xs text-gray-500">@lang('site.otp_step_terms')</p>
                        <button type="submit" class="w-full bg-sage-600 text-white py-3.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors text-lg">
                            @lang('site.otp_verify_submit')
                        </button>
                    </form>
                @else
                <form method="POST" action="{{ route('clinic.book', $clinic->slug) }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.full_name') <span class="text-red-500">*</span></label>
                        <input type="text" name="customer_name" value="{{ old('customer_name', auth('web')->user()?->name ?? ($identity['name'] ?? '')) }}"
                               required class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.phone_number') <span class="text-red-500">*</span></label>
                        <input type="tel" name="customer_phone" value="{{ old('customer_phone', auth('web')->user()?->phone ?? ($identity['phone'] ?? '')) }}"
                               required pattern="05[0-9]{8}" placeholder="05XXXXXXXX"
                               class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400" dir="ltr">
                    </div>

                    @if($clinic->services->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.requested_service')</label>
                            <select name="service_id" class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">
                                <option value="">@lang('site.not_specified')</option>
                                @foreach($clinic->services as $svc)
                                    <option value="{{ $svc->id }}" @selected(old('service_id', $service?->id) == $svc->id)>{{ $svc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.notes_optional')</label>
                        <textarea name="notes" rows="4" maxlength="1000"
                                  class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">{{ old('notes') }}</textarea>
                    </div>

                    @guest('web')
                        <p class="text-xs text-gray-500">@lang('site.otp_step_terms')</p>
                    @endguest

                    <button type="submit" class="w-full bg-sage-600 text-white py-3.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors text-lg">
                        @lang('site.submit_booking')
                    </button>
                </form>
                @endif
            </div>
        </div>

        {{-- Sidebar: Summary --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm p-6 lg:sticky lg:top-20">
                <h3 class="font-bold text-gray-800 mb-4">@lang('site.booking_summary')</h3>

                <div class="space-y-3 text-sm">
                    <div>
                        <p class="text-gray-500 mb-0.5">@lang('site.booking_target_clinic')</p>
                        <p class="font-semibold text-gray-800">{{ $clinic->name }}</p>
                    </div>

                    @if($clinic->city)
                        <div>
                            <p class="text-gray-500 mb-0.5">@lang('site.booking_target_city')</p>
                            <p class="font-medium text-gray-800">{{ $clinic->city->display_name }}</p>
                        </div>
                    @endif

                    @if($service)
                        <div>
                            <p class="text-gray-500 mb-0.5">@lang('site.booking_target_service')</p>
                            <p class="font-medium text-gray-800">{{ $service->name }}</p>
                            @if($service->price)
                                <p class="text-sage-700 font-bold mt-1">
                                    {{ number_format($service->price) }} <span class="text-xs font-normal">@lang('site.currency_sar')</span>
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <hr class="my-4 border-gray-100">

                <p class="text-xs text-gray-500 leading-relaxed">
                    @lang('site.how_not_appointment_notice')
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
