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
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4">
            @foreach($offers as $i => $offer)
                @php
                    $discount = $offer->discountPercentage();
                    $clinic   = $offer->clinic;
                    $service  = $offer->service;
                @endphp
                <div class="reveal relative" style="--reveal-delay:{{ ($i % 6) * 70 }}ms">
                    <x-save-button :model="$offer" type="offer" class="absolute top-2 end-2 z-20" />
                    {{-- Clicking an offer opens its detail page; the booking
                         deep-link lives there, not on the card. --}}
                    <a href="{{ $clinic ? route('offer.show', ['slug' => $clinic->slug, 'offer' => $offer->id]) : '#' }}"
                       class="block group bg-white rounded-3xl shadow-soft hover:shadow-soft-lg hover:-translate-y-1 transition-all duration-300 overflow-hidden h-full">
                        <div class="relative aspect-[16/10] bg-sage-mist text-sage-primary flex items-center justify-center text-4xl">
                            {{ $category->emoji ?: '🩺' }}
                            @if($discount && $discount > 0)
                                <span class="absolute top-2 start-2 bg-red-500 text-white text-[11px] font-bold px-2 py-0.5 rounded-full shadow-sm">-{{ $discount }}%</span>
                            @endif
                        </div>
                        <div class="p-3">
                            <h3 class="text-sm font-semibold text-gray-800 line-clamp-2 group-hover:text-sage-700 transition-colors">{{ $offer->title }}</h3>
                            @if($clinic)
                                <p class="text-[11px] text-gray-500 mt-0.5 line-clamp-1">{{ $clinic->name }}</p>
                            @endif
                            <div class="mt-2 flex items-baseline gap-1.5">
                                @if($offer->price !== null)
                                    <span class="text-sage-700 font-bold text-base">{{ number_format((float) $offer->price) }}<span class="text-[10px] font-normal ms-0.5"><x-riyal /></span></span>
                                @endif
                                @if($offer->old_price !== null)
                                    <span class="text-[10px] text-gray-400 line-through">{{ number_format((float) $offer->old_price) }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
