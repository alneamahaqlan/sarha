@extends('layouts.public')

@section('title', __('site.account_my_rewards'))

@php
    use App\Models\RewardVoucher;

    // Human description of what a voucher grants.
    $describe = function (RewardVoucher $v) {
        if ($v->type === RewardVoucher::TYPE_FREE_SERVICE) {
            return __('site.reward_desc_free_service', ['service' => $v->service?->name ?? '—']);
        }
        $offer = $v->offer?->title ?? '—';
        return $v->discount_type === RewardVoucher::DISCOUNT_PERCENT
            ? __('site.reward_desc_offer_percent', ['value' => rtrim(rtrim(number_format((float) $v->discount_value, 2), '0'), '.'), 'offer' => $offer])
            : __('site.reward_desc_offer_amount', ['value' => rtrim(rtrim(number_format((float) $v->discount_value, 2), '0'), '.'), 'offer' => $offer]);
    };

    $fmt = fn ($d) => $d ? $d->locale(app()->getLocale())->translatedFormat('j M Y') : null;
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="bg-gradient-to-r from-sage-600 to-sage-800 text-white rounded-2xl p-6 mb-6">
        <h1 class="text-2xl font-bold mb-2 flex items-center gap-2">
            <x-icon name="gift" class="w-6 h-6" /> {{ __('site.account_my_rewards') }}
        </h1>
        <p class="text-sage-100 text-sm">{{ __('site.reward_intro') }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">@include('public.account._nav')</div>

        <div class="lg:col-span-3 space-y-6">
            @if(session('success'))
                <div class="bg-green-50 text-green-700 border border-green-200 rounded-xl p-4 text-sm">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl p-4 text-sm">{{ $errors->first() }}</div>
            @endif

            {{-- Active / usable rewards --}}
            <section class="space-y-3">
                <h2 class="text-sm font-bold text-gray-700">{{ __('site.reward_active_section') }} ({{ $active->count() }})</h2>

                @forelse($active as $v)
                    <div class="bg-white rounded-xl shadow-sm p-5 border-s-4 border-sage-500">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <p class="font-bold text-gray-800">{{ $describe($v) }}</p>
                                @if($v->clinic)
                                    <a href="{{ route('clinic.show', $v->clinic->slug) }}" class="text-xs text-sage-600 hover:underline">
                                        {{ __('site.reward_from_clinic', ['clinic' => $v->clinic->name]) }}
                                    </a>
                                @endif
                            </div>
                            <span class="text-xs font-mono bg-sage-50 text-sage-700 px-2 py-1 rounded" dir="ltr">{{ $v->code }}</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="clock" class="w-4 h-4" />
                                {{ $v->expires_at ? __('site.reward_expires', ['date' => $fmt($v->expires_at)]) : __('site.reward_no_expiry') }}
                            </span>
                        </div>

                        {{-- Transfer by phone --}}
                        <details class="mt-3 pt-3 border-t border-gray-100">
                            <summary class="cursor-pointer text-sm text-sage-600 hover:text-sage-700 font-medium inline-flex items-center gap-1.5">
                                <x-icon name="user-plus" class="w-4 h-4" /> {{ __('site.reward_transfer_cta') }}
                            </summary>
                            <form method="POST" action="{{ route('account.rewards.transfer', $v->id) }}" class="mt-3 flex flex-wrap items-end gap-2">
                                @csrf
                                <div class="flex-1 min-w-[180px]">
                                    <label class="block text-xs text-gray-500 mb-1">{{ __('site.reward_transfer_phone_label') }}</label>
                                    <input type="tel" name="to_phone" dir="ltr" placeholder="05xxxxxxxx" required
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-sage-500 focus:border-sage-500">
                                </div>
                                <button type="submit" class="bg-sage-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-sage-700">
                                    {{ __('site.reward_transfer_submit') }}
                                </button>
                            </form>
                            <p class="text-xs text-gray-400 mt-1.5">{{ __('site.reward_transfer_hint') }}</p>
                        </details>
                    </div>
                @empty
                    <div class="bg-white rounded-xl shadow-sm p-10 text-center">
                        <x-icon name="gift" class="w-16 h-16 mx-auto mb-3 text-gray-300" />
                        <p class="text-gray-500">{{ __('site.account_no_rewards') }}</p>
                    </div>
                @endforelse
            </section>

            {{-- Used + lapsed (history) --}}
            @if($used->isNotEmpty() || $lapsed->isNotEmpty())
                <section class="space-y-2">
                    <h2 class="text-sm font-bold text-gray-700">{{ __('site.reward_history_section') }}</h2>
                    @foreach($used->concat($lapsed) as $v)
                        @php $isUsed = $v->status === RewardVoucher::STATUS_USED; @endphp
                        <div class="bg-white/70 rounded-xl border border-gray-100 p-4 flex items-center justify-between gap-3 opacity-80">
                            <div>
                                <p class="text-sm text-gray-700">{{ $describe($v) }}</p>
                                <p class="text-xs text-gray-400">{{ $v->clinic?->name }}</p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full {{ $isUsed ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-500' }}">
                                {{ __('site.reward_status.' . ($isUsed ? 'used' : ($v->status === RewardVoucher::STATUS_VOID ? 'void' : 'expired'))) }}
                            </span>
                        </div>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
