@php
    use App\Support\SmartBadges;
    $badges = isset($badgeContext) ? SmartBadges::compute($clinic, $badgeContext) : [];
@endphp

<a href="{{ route('clinic.show', $clinic->slug) }}"
   class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden block group relative">

    {{-- Smart badges overlay --}}
    @if(! empty($badges))
        <div class="absolute top-3 start-3 z-10 flex flex-col gap-1">
            @foreach($badges as $badge)
                <span class="bg-{{ $badge['color'] }}-500 text-white text-xs px-2 py-0.5 rounded-full font-semibold shadow-sm">
                    @lang($badge['label_key'])
                </span>
            @endforeach
        </div>
    @endif

    @if($clinic->logo)
        <div class="h-40 overflow-hidden bg-gray-100">
            <img src="{{ Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        </div>
    @else
        <div class="h-40 bg-gradient-to-br from-teal-100 to-teal-200 flex items-center justify-center">
            <span class="text-5xl">🏥</span>
        </div>
    @endif

    <div class="p-4">
        <div class="flex items-start justify-between mb-2 gap-2">
            <h3 class="font-bold text-gray-800 group-hover:text-teal-600 transition-colors leading-snug">
                {{ $clinic->name }}
            </h3>
            @if($clinic->is_featured && empty($badges))
                <span class="bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full whitespace-nowrap shrink-0">{{ __('site.featured') }} ⭐</span>
            @endif
        </div>

        {{-- Rating + Location --}}
        <div class="flex items-center gap-3 text-sm text-gray-500 mb-3 flex-wrap">
            @if(($clinic->google_reviews_avg_rating ?? 0) > 0)
                <span class="flex items-center gap-1">
                    <span class="text-yellow-500">★</span>
                    <span class="font-semibold text-gray-700">{{ number_format($clinic->google_reviews_avg_rating, 1) }}</span>
                </span>
            @endif
            <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                {{ $clinic->city->display_name ?? '' }}
            </span>
        </div>

        @if($clinic->categories->isNotEmpty())
            <div class="flex flex-wrap gap-1">
                @foreach($clinic->categories->take(3) as $cat)
                    <span class="bg-teal-50 text-teal-600 text-xs px-2 py-0.5 rounded-full">
                        {{ $cat->emoji ?? '' }} {{ $cat->display_name }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</a>
