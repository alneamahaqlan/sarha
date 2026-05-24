@extends('layouts.public')

@section('title', __('site.quote_request_title'))

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span class="mx-2">/</span>
        <a href="{{ route('quotes.board') }}" class="hover:text-sage-600">@lang('site.quotes_board_title')</a>
        <span class="mx-2">/</span>
        <span class="text-gray-800">@lang('site.quote_request_title')</span>
    </nav>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">@lang('site.quote_request_title')</h1>
        <p class="text-sm text-gray-500 mb-6">@lang('site.quote_request_subtitle')</p>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 text-sm mb-5">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if(session('otp_required'))
            <div class="bg-sage-50 border border-sage-200 text-sage-900 rounded-lg p-4 text-sm mb-5 space-y-1">
                <p class="font-semibold">@lang('site.otp_step_title')</p>
                <p>@lang('site.otp_step_intro')</p>
                <p class="text-xs text-sage-700">{{ __('site.otp_sent_to', ['phone' => session('otp_phone')]) }}</p>
                @if(session('dev_code'))
                    <p class="text-xs font-mono bg-white inline-block px-2 py-1 rounded border border-sage-200">DEV: {{ session('dev_code') }}</p>
                @endif
            </div>
            <form method="POST" action="{{ route('quotes.verify') }}" class="space-y-5">
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
            <form method="POST" action="{{ route('quotes.store') }}" class="space-y-5">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.full_name') <span class="text-red-500">*</span></label>
                        <input type="text" name="customer_name" value="{{ old('customer_name', auth('web')->user()?->name ?? ($identity['name'] ?? '')) }}"
                               required class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.phone_number') <span class="text-red-500">*</span></label>
                        <input type="tel" name="customer_phone" value="{{ old('customer_phone', auth('web')->user()?->phone ?? ($identity['phone'] ?? '')) }}"
                               required pattern="05[0-9]{8}" placeholder="05XXXXXXXX" dir="ltr"
                               class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.service_short_name') <span class="text-red-500">*</span></label>
                    <input type="text" name="service_name" value="{{ old('service_name') }}" required maxlength="255"
                           class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.quote_details_label') <span class="text-red-500">*</span></label>
                    <textarea name="description" rows="4" required minlength="10" maxlength="2000" placeholder="{{ __('site.quote_details_placeholder') }}"
                              class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">{{ old('description') }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">@lang('site.quote_details_hint')</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">@lang('site.quote_cities_label') <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-56 overflow-auto border border-gray-100 rounded-lg p-3">
                        @foreach($cities as $city)
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" name="city_ids[]" value="{{ $city->id }}"
                                       @checked(collect(old('city_ids'))->contains($city->id))
                                       class="h-4 w-4 rounded border-gray-300 text-sage-600">
                                {{ $city->display_name }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 mt-1">@lang('site.quote_cities_hint')</p>
                </div>

                @guest('web')
                    <p class="text-xs text-gray-500">@lang('site.otp_step_terms')</p>
                @endguest

                <button type="submit" class="w-full bg-sage-600 text-white py-3.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors text-lg">
                    @lang('site.quote_submit')
                </button>
            </form>
        @endif
    </div>
</div>
@endsection
