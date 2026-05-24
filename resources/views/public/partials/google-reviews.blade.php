{{--
    Google reviews block.
    Expects: $clinic with googleReviews loaded + google_reviews_avg_rating + google_reviews_count
--}}
@php
    $avgRating = $clinic->google_reviews_avg_rating ?? 0;
    $reviewsCount = $clinic->google_reviews_count ?? 0;
    $distribution = $reviewsCount > 0
        ? $clinic->googleReviews->groupBy('rating')->map->count()
        : collect();
@endphp

<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.reviews_block_title')</h2>

    @if($reviewsCount === 0)
        <div class="text-center py-8 text-gray-400">
            <x-icon name="star" class="w-10 h-10 mx-auto mb-2 text-gray-300" />
            <p class="text-sm">@lang('site.reviews_unlinked')</p>
        </div>
    @else
        {{-- Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-5 mb-5 border-b border-gray-100">
            <div class="text-center">
                <div class="text-5xl font-bold text-gray-900 mb-1">
                    {{ number_format($avgRating, 1) }}
                </div>
                <div class="flex justify-center mb-1 text-yellow-400">
                    @for($i = 1; $i <= 5; $i++)
                        @if($i <= round($avgRating))
                            ★
                        @else
                            <span class="text-gray-200">★</span>
                        @endif
                    @endfor
                </div>
                <p class="text-sm text-gray-500">
                    {{ __('site.reviews_count_label', ['count' => $reviewsCount]) }}
                </p>
            </div>
            <div class="space-y-1.5">
                @for($star = 5; $star >= 1; $star--)
                    @php
                        $count = $distribution->get($star, 0);
                        $pct = $reviewsCount > 0 ? round(($count / $reviewsCount) * 100) : 0;
                    @endphp
                    <div class="flex items-center gap-2 text-sm">
                        <span class="w-3 text-gray-500">{{ $star }}</span>
                        <span class="text-yellow-400">★</span>
                        <div class="flex-1 bg-gray-100 h-2 rounded-full overflow-hidden">
                            <div class="bg-yellow-400 h-full" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="w-8 text-end text-gray-500 text-xs">{{ $count }}</span>
                    </div>
                @endfor
            </div>
        </div>

        {{-- List --}}
        <div class="space-y-4 max-h-96 overflow-y-auto">
            @foreach($clinic->googleReviews as $review)
                <div class="flex gap-3">
                    @if($review->reviewer_photo)
                        <img src="{{ $review->reviewer_photo }}" alt="{{ $review->reviewer_name }}" loading="lazy"
                             class="w-10 h-10 rounded-full object-cover">
                    @else
                        <div class="w-10 h-10 bg-teal-100 text-teal-700 rounded-full flex items-center justify-center font-bold flex-shrink-0">
                            {{ mb_substr($review->reviewer_name ?? 'G', 0, 1) }}
                        </div>
                    @endif
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-gray-800 text-sm">{{ $review->reviewer_name }}</p>
                            <span class="text-xs text-gray-400">
                                {{ $review->reviewed_at?->diffForHumans() }}
                            </span>
                        </div>
                        <div class="text-yellow-400 text-xs mb-1">
                            @for($i = 1; $i <= 5; $i++){{ $i <= $review->rating ? '★' : '☆' }}@endfor
                        </div>
                        @if($review->review_text)
                            <p class="text-sm text-gray-600 leading-relaxed">{{ $review->review_text }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
