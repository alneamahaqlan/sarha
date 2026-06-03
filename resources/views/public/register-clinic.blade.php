@extends('layouts.public')

@section('title', __('site.register_clinic_title'))
@section('description', __('site.register_clinic_subtitle'))

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10">

    {{-- Header --}}
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">@lang('site.register_clinic_title')</h1>
        <p class="text-gray-500">@lang('site.register_clinic_subtitle')</p>
    </div>

    @if(session('success'))
        <div class="bg-white rounded-2xl shadow-sm p-10 text-center">
            <x-icon name="check-circle" class="w-16 h-16 mx-auto mb-4 text-emerald-500" />
            <h2 class="text-xl font-bold text-gray-800 mb-2">@lang('site.register_clinic_success_title')</h2>
            <p class="text-gray-500 mb-6">{{ session('success') }}</p>
            <a href="{{ route('home') }}" class="bg-sage-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors inline-block">
                @lang('site.booking_back_home')
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('clinic.register.submit') }}" class="bg-white rounded-2xl shadow-sm p-6 md:p-8 space-y-5">
            @csrf

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.register_clinic_name') <span class="text-red-500">*</span></label>
                    <input type="text" name="clinic_name" value="{{ old('clinic_name') }}" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.register_contact_name') <span class="text-red-500">*</span></label>
                    <input type="text" name="contact_name" value="{{ old('contact_name') }}" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.phone_number') <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" required dir="ltr" placeholder="05XXXXXXXX"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.contact_info')</label>
                    <input type="email" name="email" value="{{ old('email') }}" dir="ltr"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.register_city') <span class="text-red-500">*</span></label>
                    <select name="city_id" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                        <option value="">@lang('site.search_all_cities')</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>{{ $city->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.register_district')</label>
                    <input type="text" name="district" value="{{ old('district') }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.register_license')</label>
                    <input type="text" name="license_number" value="{{ old('license_number') }}" dir="ltr"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.register_tax_number')</label>
                    <input type="text" name="tax_number" value="{{ old('tax_number') }}" dir="ltr"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.register_commercial_registration')</label>
                    <input type="text" name="commercial_registration" value="{{ old('commercial_registration') }}" dir="ltr"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.register_notes')</label>
                    <textarea name="notes" rows="3"
                              class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">{{ old('notes') }}</textarea>
                </div>
            </div>

            <p class="text-xs text-gray-400">@lang('site.register_clinic_disclaimer')</p>

            <button type="submit" class="w-full bg-sage-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-sage-700 transition-colors">
                @lang('site.register_clinic_submit')
            </button>
        </form>
    @endif
</div>
@endsection
