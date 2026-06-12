@php
    use App\Support\SmartBadges;
    $badges = isset($badgeContext) ? SmartBadges::compute($clinic, $badgeContext) : [];
@endphp

<a href="{{ route('clinic.show', $clinic->slug) }}"
   class="bg-white rounded-3xl shadow-soft hover:shadow-soft-lg hover:-translate-y-1 transition-all duration-300 overflow-hidden block group relative">

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

    {{-- Compare toggle (search results) — handled by the global compare tray script.
         Outer button reserves a full 44×44 tap zone (per WCAG); inner `compare-chip`
         span carries the small visible circle so the card layout doesn't change.
         The `.is-selected` styling targets the inner chip so existing JS still
         flips the visual state by toggling the class on the button. --}}
    @if($compare ?? false)
        <button type="button"
                class="compare-toggle absolute top-1 end-1 z-20 inline-flex items-center justify-center min-h-touch min-w-touch rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-sage-500"
                data-compare-type="clinic"
                data-compare-id="{{ $clinic->id }}"
                data-compare-name="{{ $clinic->name }}"
                aria-pressed="false"
                aria-label="@lang('site.compare_add')"
                title="@lang('site.compare_add')">
            <span class="compare-chip flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-gray-500 shadow-sm ring-1 ring-gray-200 transition-colors group-[.compare-toggle:hover]:text-sage-600 [.compare-toggle.is-selected_&]:bg-sage-600 [.compare-toggle.is-selected_&]:text-white [.compare-toggle.is-selected_&]:ring-sage-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </span>
        </button>
    @endif

    {{-- aspect-ratio reserves the slot in the layout BEFORE the image
         decodes, so the rest of the card doesn't jump down by 160px
         when the network is slow. The 5/3 ratio matches the visual
         feel of the previous fixed h-40 on a typical card width. --}}
    @if($clinic->logo)
        <div class="aspect-[5/3] overflow-hidden bg-gray-100">
            <img src="{{ Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}" loading="lazy"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        </div>
    @else
        <div class="aspect-[5/3] bg-gradient-to-br from-sage-100 to-sage-200 flex items-center justify-center">
            <x-icon name="building" class="w-12 h-12 text-sage-600/40" />
        </div>
    @endif

    <div class="p-4">
        <div class="flex items-start justify-between mb-2 gap-2">
            <h3 class="font-display font-bold text-charcoal group-hover:text-sage-600 transition-colors leading-snug">
                {{ $clinic->name }}
            </h3>
            @if($clinic->is_featured && empty($badges))
                <span class="inline-flex items-center gap-1 bg-gold-whisper text-gold-deep text-xs px-2 py-0.5 rounded-full whitespace-nowrap shrink-0"><x-icon name="star-solid" class="w-3 h-3" /> {{ __('site.featured') }}</span>
            @endif
        </div>

        {{-- Rating + Location --}}
        <div class="flex items-center gap-3 text-sm text-gray-500 mb-3 flex-wrap">
            @if(($clinic->google_reviews_avg_rating ?? 0) > 0)
                <span class="flex items-center gap-1">
                    <span class="text-gold-primary">★</span>
                    <span class="font-semibold text-charcoal">{{ number_format($clinic->google_reviews_avg_rating, 1) }}</span>
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
                    <span class="inline-flex items-center gap-1 bg-sage-50 text-sage-600 text-xs px-2 py-0.5 rounded-full">
                        <x-category-icon :emoji="$cat->emoji" :icon="$cat->icon" class="w-3 h-3" /> {{ $cat->display_name }}
                    </span>
                @endforeach
            </div>
        @endif

        {{-- Starting price — shown whenever the list query loaded min_price --}}
        @if(! is_null($clinic->min_price ?? null))
            <p class="mt-3 text-sm font-semibold text-sage-700">
                {{ __('site.starting_from', ['amount' => number_format($clinic->min_price)]) }} <x-riyal />
            </p>
        @endif
    </div>
</a>
