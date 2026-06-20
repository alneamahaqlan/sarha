@extends('layouts.public')

@section('title', __('site.account_my_reviews'))

@php
    // Filled/empty stars for a 1-5 rating.
    $stars = function ($n) {
        $n = (int) $n;
        $out = '';
        for ($i = 1; $i <= 5; $i++) {
            $out .= $i <= $n ? '★' : '☆';
        }
        return $out;
    };
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="bg-gradient-to-r from-sage-600 to-sage-800 text-white rounded-2xl p-6 mb-6">
        <h1 class="text-2xl font-bold mb-2 flex items-center gap-2">
            <x-icon name="star-solid" class="w-6 h-6" /> {{ __('site.account_my_reviews') }}
        </h1>
        <p class="text-sage-100 text-sm">{{ __('site.review_account_intro') }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">@include('public.account._nav')</div>

        <div class="lg:col-span-3 space-y-6">
            @if(session('success'))
                <div class="bg-green-50 text-green-700 border border-green-200 rounded-xl p-4 text-sm">{{ session('success') }}</div>
            @endif

            {{-- Pending — visits awaiting the patient's review --}}
            <section class="space-y-3">
                <h2 class="text-sm font-bold text-gray-700">{{ __('site.review_pending_section') }} ({{ $pending->count() }})</h2>
                @forelse($pending as $r)
                    <div class="bg-white rounded-xl shadow-sm p-5 flex items-center justify-between gap-3 border-s-4 border-gold-400">
                        <div>
                            <a href="{{ route('clinic.show', $r->clinic->slug) }}" class="font-bold text-gray-800 hover:text-sage-600">{{ $r->clinic->name }}</a>
                            <p class="text-xs text-gray-500 mt-0.5">{{ __('site.review_pending_hint') }}</p>
                        </div>
                        <a href="{{ route('review.form', $r->id) }}"
                           class="shrink-0 bg-sage-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-sage-700">
                            {{ __('site.review_rate_now') }}
                        </a>
                    </div>
                @empty
                    <div class="bg-white rounded-xl shadow-sm p-8 text-center text-sm text-gray-500">{{ __('site.review_no_pending') }}</div>
                @endforelse
            </section>

            {{-- Published — the patient's submitted reviews --}}
            @if($published->isNotEmpty())
                <section class="space-y-3">
                    <h2 class="text-sm font-bold text-gray-700">{{ __('site.review_published_section') }}</h2>
                    @foreach($published as $r)
                        <div class="bg-white rounded-xl shadow-sm p-5">
                            <div class="flex items-center justify-between gap-3">
                                <a href="{{ route('clinic.show', $r->clinic->slug) }}" class="font-bold text-gray-800 hover:text-sage-600">{{ $r->clinic->name }}</a>
                                <span class="text-gold-500 text-lg" dir="ltr" title="{{ $r->clinic_rating }}/5">{{ $stars($r->clinic_rating) }}</span>
                            </div>
                            @if($r->doctor && $r->doctor_rating)
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $r->doctor->name }}: <span class="text-gold-500" dir="ltr">{{ $stars($r->doctor_rating) }}</span>
                                </p>
                            @endif
                            @if($r->comment)
                                <p class="text-sm text-gray-700 mt-2">{{ $r->comment }}</p>
                            @endif
                            @if($r->hasReply())
                                <div class="mt-3 rounded-lg bg-sage-50 border border-sage-100 p-3">
                                    <p class="text-[11px] font-semibold text-sage-700 mb-0.5">
                                        {{ __('site.review_clinic_reply_by', ['name' => $r->clinic_replied_by_name_snapshot ?: $r->clinic->name]) }}
                                    </p>
                                    <p class="text-sm text-gray-700">{{ $r->clinic_reply_text }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
