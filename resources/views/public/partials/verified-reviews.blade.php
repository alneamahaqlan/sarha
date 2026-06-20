{{--
    Verified (attendance-backed) reviews block.
    Expects: $clinic with verifiedReviews loaded (visible scope) +
    verified_clinic_avg + verified_doctor_avg + verified_reviews_count.
    Distinct from Google sync: every row here is backed by a confirmed visit.
--}}
@php
    $vAvg   = (float) ($clinic->verified_clinic_avg ?? 0);
    $vDocAvg= $clinic->verified_doctor_avg !== null ? (float) $clinic->verified_doctor_avg : null;
    $vCount = (int) ($clinic->verified_reviews_count ?? 0);
    $vDist  = $vCount > 0 ? $clinic->verifiedReviews->groupBy('clinic_rating')->map->count() : collect();
    $vStars = function ($n) {
        $n = (int) round($n); $out = '';
        for ($i = 1; $i <= 5; $i++) { $out .= $i <= $n ? '★' : '☆'; }
        return $out;
    };
@endphp

<div class="bg-white rounded-xl shadow-sm p-6 border border-sage-100">
    <div class="flex items-center justify-between gap-3 mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <x-icon name="shield-check" class="w-5 h-5 text-sage-600" />
                @lang('site.verified_reviews_title')
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">@lang('site.verified_reviews_subtitle')</p>
        </div>
        <span class="shrink-0 inline-flex items-center gap-1 bg-sage-50 text-sage-700 border border-sage-200 px-2.5 py-1 rounded-full text-xs font-semibold">
            <x-icon name="shield-check" class="w-3.5 h-3.5" /> @lang('site.verified_badge')
        </span>
    </div>

    {{-- Summary — average is the headline so no single review dominates. --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-5 mb-5 border-b border-gray-100">
        <div class="text-center">
            <div class="text-5xl font-bold text-gray-900 mb-1">{{ number_format($vAvg, 1) }}</div>
            <div class="flex justify-center mb-1 text-gold-500 text-lg" dir="ltr">
                @for($i = 1; $i <= 5; $i++)<span class="{{ $i <= round($vAvg) ? '' : 'text-gray-200' }}">★</span>@endfor
            </div>
            <p class="text-sm text-gray-500">{{ __('site.verified_reviews_count_label', ['count' => $vCount]) }}</p>
            @if($vDocAvg !== null)
                <p class="text-xs text-gray-400 mt-1">{{ __('site.verified_doctor_avg_label', ['avg' => number_format($vDocAvg, 1)]) }}</p>
            @endif
        </div>
        <div class="space-y-1.5">
            @for($star = 5; $star >= 1; $star--)
                @php $c = $vDist->get($star, 0); $pct = $vCount > 0 ? round(($c / $vCount) * 100) : 0; @endphp
                <div class="flex items-center gap-2 text-sm">
                    <span class="w-3 text-gray-500">{{ $star }}</span>
                    <span class="text-gold-500">★</span>
                    <div class="flex-1 bg-gray-100 h-2 rounded-full overflow-hidden">
                        <div class="bg-gold-500 h-full" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="w-8 text-end text-gray-500 text-xs">{{ $c }}</span>
                </div>
            @endfor
        </div>
    </div>

    {{-- List — scrollable so a long history never visually dominates. --}}
    <div class="space-y-4 max-h-96 overflow-y-auto">
        @foreach($clinic->verifiedReviews as $review)
            <div class="flex gap-3">
                <div class="w-10 h-10 bg-sage-100 text-sage-700 rounded-full flex items-center justify-center font-bold flex-shrink-0">
                    {{ mb_substr($review->customer_name ?? '؟', 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold text-gray-800 text-sm flex items-center gap-1.5">
                            {{ $review->customer_name ?: __('site.review_anon') }}
                            <span class="inline-flex items-center gap-0.5 text-[10px] text-sage-700" title="@lang('site.verified_badge')">
                                <x-icon name="shield-check" class="w-3 h-3" />
                            </span>
                        </p>
                        <span class="text-xs text-gray-400 shrink-0">{{ $review->submitted_at?->diffForHumans() }}</span>
                    </div>
                    <div class="text-gold-500 text-xs mb-1" dir="ltr">
                        @for($i = 1; $i <= 5; $i++){{ $i <= $review->clinic_rating ? '★' : '☆' }}@endfor
                    </div>
                    @if($review->doctor && $review->doctor_rating)
                        <p class="text-xs text-gray-500 mb-1">
                            {{ __('site.verified_review_doctor', ['name' => $review->doctor->name]) }}
                            <span class="text-gold-500" dir="ltr">@for($i = 1; $i <= 5; $i++){{ $i <= $review->doctor_rating ? '★' : '☆' }}@endfor</span>
                        </p>
                    @endif
                    @if($review->comment)
                        <p class="text-sm text-gray-600 leading-relaxed">{{ $review->comment }}</p>
                    @endif

                    @if($review->hasReply())
                        <div class="mt-2 rounded-lg bg-sage-50 border border-sage-100 p-3">
                            <p class="text-[11px] font-semibold text-sage-700 mb-0.5">
                                {{ __('site.review_clinic_reply_by', ['name' => $review->clinic_replied_by_name_snapshot ?: $clinic->name]) }}
                            </p>
                            <p class="text-sm text-gray-700">{{ $review->clinic_reply_text }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
