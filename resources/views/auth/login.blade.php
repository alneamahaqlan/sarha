@extends('layouts.public')

@section('title', __('site.login_title'))

@section('content')
<div class="min-h-screen flex items-center justify-center px-4 py-12 bg-gray-50">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-sm p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-sage-600 mb-1">@lang('site.brand')</h1>
                <p class="text-gray-500 text-sm">@lang('site.login_subtitle')</p>
            </div>

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm mb-5">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @if(!session('otp_sent'))
                {{-- Step 1: Enter phone --}}
                <form method="POST" action="{{ route('login.otp') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">@lang('site.phone_number')</label>
                        <input type="tel" name="phone" value="{{ old('phone') }}" required
                               placeholder="05XXXXXXXX"
                               inputmode="tel" autocomplete="tel" pattern="05[0-9]{8}"
                               class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400 text-center tracking-wider"
                               dir="ltr">
                    </div>
                    <button type="submit" class="w-full bg-sage-600 text-white py-3 rounded-xl font-semibold hover:bg-sage-700 transition-colors">
                        @lang('site.send_otp')
                    </button>
                </form>
            @else
                {{-- Step 2: Enter OTP --}}
                <form method="POST" action="{{ route('login.verify') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="phone" value="{{ old('phone') }}">
                    <div class="text-center text-sm text-gray-600 mb-2">
                        {{ __('site.otp_sent_to', ['phone' => old('phone')]) }}
                        @if(session('dev_code'))
                            <br><span class="text-sage-600 font-bold">{{ __('site.dev_code', ['code' => session('dev_code')]) }}</span>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">@lang('site.otp_label')</label>
                        <input type="text" name="code" maxlength="6" required
                               placeholder="XXXXXX"
                               inputmode="numeric" pattern="\d{6}"
                               class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400 text-center tracking-[0.5em] text-xl"
                               dir="ltr" autocomplete="one-time-code">
                    </div>
                    <button type="submit" class="w-full bg-sage-600 text-white py-3 rounded-xl font-semibold hover:bg-sage-700 transition-colors">
                        @lang('site.verify_and_enter')
                    </button>
                </form>
                <div class="text-center mt-4">
                    <a href="{{ route('login') }}" class="text-sm text-gray-500 hover:text-sage-600">@lang('site.change_phone')</a>
                </div>
            @endif

            {{-- Create clinic account --}}
            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                <a href="{{ route('clinic.register') }}"
                   class="inline-flex items-center justify-center gap-2 w-full border border-sage-200 text-sage-700 py-3 rounded-xl font-semibold hover:bg-sage-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    @lang('site.create_clinic_account')
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
