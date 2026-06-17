@extends('layouts.public')

@section('title', __('site.review_form_title', ['clinic' => $review->clinic->name]))

@section('content')
<div class="max-w-xl mx-auto px-4 py-10">

    <div class="bg-white rounded-2xl shadow-sm p-6 sm:p-8">
        <div class="flex items-center gap-2 mb-1">
            <x-icon name="star-solid" class="w-6 h-6 text-gold-500" />
            <h1 class="text-2xl font-bold text-gray-900">{{ __('site.review_form_title', ['clinic' => $review->clinic->name]) }}</h1>
        </div>
        <p class="text-sm text-gray-500 mb-6">{{ __('site.review_form_subtitle') }}</p>

        @if(session('error'))
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        @if($alreadySubmitted)
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-6 text-center">
                <x-icon name="check-circle" class="w-12 h-12 mx-auto mb-2 text-green-500" />
                <p class="text-green-800 font-semibold">{{ __('site.review_already_submitted') }}</p>
                <a href="{{ route('clinic.show', $review->clinic->slug) }}" class="inline-block mt-3 text-sm text-sage-600 hover:underline">
                    {{ __('site.review_back_to_clinic') }}
                </a>
            </div>
        @else
            <form method="POST" action="{{ $action }}" class="space-y-6">
                @csrf
                <x-form.errors />

                {{-- Clinic experience (required) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-2">
                        {{ __('site.review_clinic_rating') }} <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2" dir="ltr">
                        @for($i = 1; $i <= 5; $i++)
                            <label class="cursor-pointer">
                                <input type="radio" name="clinic_rating" value="{{ $i }}" class="peer sr-only" @checked((int) old('clinic_rating') === $i) required>
                                <span class="flex items-center justify-center w-11 h-11 rounded-lg border border-gray-200 text-gray-600 font-semibold transition peer-checked:bg-sage-600 peer-checked:text-white peer-checked:border-sage-600 hover:border-sage-400">{{ $i }}</span>
                            </label>
                        @endfor
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">{{ __('site.review_scale_hint') }}</p>
                </div>

                {{-- Doctor (optional) --}}
                @if($doctors->isNotEmpty())
                    <div class="space-y-3 border-t border-gray-100 pt-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.review_doctor') }}</label>
                            <x-form.select name="doctor_id">
                                <option value="">{{ __('site.review_doctor_none') }}</option>
                                @foreach($doctors as $doc)
                                    <option value="{{ $doc->id }}" @selected((int) old('doctor_id') === $doc->id)>
                                        {{ $doc->name }}@if($doc->specialty) — {{ $doc->specialty }}@endif
                                    </option>
                                @endforeach
                            </x-form.select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-2">{{ __('site.review_doctor_rating') }}</label>
                            <div class="flex gap-2" dir="ltr">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="doctor_rating" value="{{ $i }}" class="peer sr-only" @checked((int) old('doctor_rating') === $i)>
                                        <span class="flex items-center justify-center w-11 h-11 rounded-lg border border-gray-200 text-gray-600 font-semibold transition peer-checked:bg-sage-600 peer-checked:text-white peer-checked:border-sage-600 hover:border-sage-400">{{ $i }}</span>
                                    </label>
                                @endfor
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Comment (optional) --}}
                <div class="border-t border-gray-100 pt-5">
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.review_comment') }}</label>
                    <textarea name="comment" rows="4" maxlength="2000"
                              placeholder="{{ __('site.review_comment_placeholder') }}"
                              class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">{{ old('comment') }}</textarea>
                </div>

                <p class="text-xs text-gray-400">{{ __('site.review_public_notice') }}</p>

                <button type="submit" class="w-full bg-sage-600 text-white py-3.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors text-lg">
                    {{ __('site.review_submit') }}
                </button>
            </form>
        @endif
    </div>
</div>
@endsection
