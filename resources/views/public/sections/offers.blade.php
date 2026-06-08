{{-- Latest Offer entities across all clinics. $data['offers'] is a Collection of Offer.
     Each card: image (fallback to linked service's image or category emoji),
     discount %, price, clinic, CTA → booking form for service-linked offers
     or the clinic page for general promos. --}}
@php $offers = $data['offers'] ?? collect(); @endphp
<section class="py-16 px-4 bg-gradient-to-b from-white to-sage-mist/30">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8">
            <div>
                <p class="reveal text-sm font-semibold tracking-widest text-gold-deep uppercase mb-2">@lang('site.featured_eyebrow')</p>
                <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">
                    {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.home_offers_title')) }}
                </h2>
                <p class="reveal text-gray-500 text-sm mt-1" style="--reveal-delay:140ms">@lang('site.home_offers_subtitle')</p>
            </div>
            <a href="{{ route('search', ['sort' => 'cheapest']) }}" class="reveal text-sage-600 text-sm font-semibold hover:text-sage-700 inline-flex items-center gap-1 whitespace-nowrap">
                @lang('site.view_all') <span class="rtl:rotate-180">→</span>
            </a>
        </div>

        @if($offers->isEmpty())
            <div class="bg-white rounded-2xl p-10 text-center text-gray-400 ring-1 ring-gray-100">
                @lang('site.home_no_offers')
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4">
                @foreach($offers as $i => $offer)
                    @php
                        $discount = $offer->discountPercentage();
                        $clinic   = $offer->clinic;
                        $service  = $offer->service;
                        $imagePath = $offer->effectiveImage();
                        $imageUrl  = $imagePath ? \Illuminate\Support\Facades\Storage::url($imagePath) : null;
                        // Clicking an offer goes to its detail page, where the
                        // booking deep-link lives — never straight to booking.
                        $href = $clinic
                            ? route('offer.show', ['slug' => $clinic->slug, 'offer' => $offer->id])
                            : '#';
                    @endphp
                    <div class="reveal relative" style="--reveal-delay:{{ ($i % 6) * 70 }}ms">
                        <x-save-button :model="$offer" type="offer" class="absolute top-3 end-3 z-20" />
                        <a href="{{ $href }}"
                           class="block group bg-white rounded-2xl ring-1 ring-gray-100 hover:shadow-xl hover:-translate-y-1 transition-all overflow-hidden h-full">
                            <div class="relative aspect-[16/10] bg-gradient-to-br from-sage-mist to-gold-whisper flex items-center justify-center text-5xl">
                                @if($imageUrl)
                                    <img src="{{ $imageUrl }}"
                                         alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
                                @elseif($service?->categories?->first()?->emoji)
                                    <span aria-hidden="true">{{ $service->categories->first()->emoji }}</span>
                                @endif
                                @if($discount && $discount > 0)
                                    <span class="absolute top-3 start-3 bg-red-500 text-white text-xs font-bold px-2.5 py-1 rounded-full shadow-md">
                                        {{ __('site.home_discount_off', ['pct' => $discount]) }}
                                    </span>
                                @endif
                                @if($offer->is_featured)
                                    <span class="absolute bottom-3 start-3 inline-flex items-center gap-1 bg-gold-deep text-white text-[11px] font-bold px-2 py-1 rounded-full shadow-md">
                                        <x-icon name="star-solid" class="w-3 h-3" /> @lang('site.featured')
                                    </span>
                                @endif
                            </div>
                            <div class="p-3">
                                <h3 class="text-sm font-semibold text-gray-800 line-clamp-1 group-hover:text-sage-700 transition-colors">{{ $offer->title }}</h3>
                                @if($clinic)
                                    <p class="text-[11px] text-gray-500 mt-0.5 line-clamp-1">{{ $clinic->name }}@if($clinic->city) · {{ $clinic->city->display_name ?? $clinic->city->name }}@endif</p>
                                @endif
                                <div class="mt-2 flex items-end justify-between gap-1">
                                    <div>
                                        @if($offer->old_price !== null)
                                            <span class="block text-[10px] text-gray-400 line-through">{{ number_format((float) $offer->old_price) }}</span>
                                        @endif
                                        @if($offer->price !== null)
                                            <span class="text-sage-700 font-bold text-base">{{ number_format((float) $offer->price) }}<span class="text-[10px] font-normal ms-0.5"><x-riyal /></span></span>
                                        @endif
                                    </div>
                                    <span class="hidden sm:inline-flex items-center gap-1 text-[11px] font-semibold text-sage-600 group-hover:text-sage-700"><span class="rtl:rotate-180">→</span></span>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
