{{-- Offers filtered by a single category. $data['category'] + $data['offers'] (Offer entities). --}}
@php
    $category = $data['category'] ?? null;
    $offers = $data['offers'] ?? collect();
@endphp
@if($category && $offers->isNotEmpty())
<section class="py-12 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-6">
            <div class="flex items-center gap-3">
                @if($category->emoji)
                    <span class="text-3xl" aria-hidden="true">{{ $category->emoji }}</span>
                @endif
                <h2 class="reveal font-display text-2xl md:text-3xl font-bold text-charcoal">
                    {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.home_category_offers_title', ['name' => $category->display_name ?? $category->name])) }}
                </h2>
            </div>
            <a href="{{ route('search', ['category' => $category->id]) }}" class="reveal text-sage-600 text-sm font-semibold hover:text-sage-700 inline-flex items-center gap-1 whitespace-nowrap">
                @lang('site.view_all') <span class="rtl:rotate-180">→</span>
            </a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
            @foreach($offers as $i => $offer)
                @php
                    $discount = $offer->discountPercentage();
                    $clinic   = $offer->clinic;
                    $service  = $offer->service;
                @endphp
                <div class="reveal relative" style="--reveal-delay:{{ ($i % 3) * 100 }}ms">
                    <x-save-button :model="$offer" type="offer" class="absolute top-3 end-3 z-20" />
                    {{-- Clicking an offer opens its detail page; the booking
                         deep-link lives there, not on the card. --}}
                    <a href="{{ $clinic ? route('offer.show', ['slug' => $clinic->slug, 'offer' => $offer->id]) : '#' }}"
                       class="block group bg-white rounded-2xl ring-1 ring-gray-100 hover:shadow-lg hover:ring-gold-soft transition-all overflow-hidden h-full">
                        <div class="p-5 flex items-start gap-4">
                            <div class="shrink-0 w-16 h-16 rounded-xl bg-sage-mist text-sage-primary flex items-center justify-center text-2xl">
                                {{ $category->emoji ?: '🩺' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-semibold text-gray-800 line-clamp-2 group-hover:text-sage-700 transition-colors">{{ $offer->title }}</h3>
                                    @if($discount && $discount > 0)
                                        <span class="shrink-0 bg-red-50 text-red-700 text-xs font-bold px-2 py-0.5 rounded-full">-{{ $discount }}%</span>
                                    @endif
                                </div>
                                @if($clinic)
                                    <p class="text-xs text-gray-500 mt-1 line-clamp-1">{{ $clinic->name }}</p>
                                @endif
                                <div class="mt-2 flex items-baseline gap-2">
                                    @if($offer->price !== null)
                                        <span class="text-sage-700 font-bold">{{ number_format((float) $offer->price) }}<span class="text-xs font-normal ms-1">@lang('site.currency_sar')</span></span>
                                    @endif
                                    @if($offer->old_price !== null)
                                        <span class="text-xs text-gray-400 line-through">{{ number_format((float) $offer->old_price) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
