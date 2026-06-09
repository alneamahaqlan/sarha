{{-- Latest Offer entities across all clinics. $data['offers'] is a Collection of Offer.
     Each card: image (fallback to linked service's image or category emoji),
     discount %, price, clinic, CTA → booking form for service-linked offers
     or the clinic page for general promos. --}}
@php $offers = $data['offers'] ?? collect(); @endphp
<section class="py-20 md:py-28 px-4 bg-gradient-to-b from-white to-sage-mist/30">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8">
            <div>
                <p class="reveal text-sm font-semibold tracking-widest text-gold-deep uppercase mb-2">@lang('site.featured_eyebrow')</p>
                <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">
                    {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.home_offers_title')) }}
                </h2>
                <p class="reveal text-slate-warm text-sm mt-1" style="--reveal-delay:140ms">@lang('site.home_offers_subtitle')</p>
            </div>
            <a href="{{ route('search', ['sort' => 'cheapest']) }}" class="reveal text-sage-600 text-sm font-semibold hover:text-sage-700 inline-flex items-center gap-1 whitespace-nowrap">
                @lang('site.view_all') <span class="rtl:rotate-180">→</span>
            </a>
        </div>

        @if($offers->isEmpty())
            <div class="bg-white rounded-3xl shadow-soft p-10 text-center text-gray-400">
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
                        $rating    = $clinic?->google_reviews_avg_rating;
                        $savings   = ($offer->old_price !== null && $offer->price !== null && (float) $offer->old_price > (float) $offer->price)
                            ? (float) $offer->old_price - (float) $offer->price : null;
                        // Clicking an offer goes to its detail page, where the
                        // booking deep-link lives — never straight to booking.
                        $href = $clinic
                            ? route('offer.show', ['slug' => $clinic->slug, 'offer' => $offer->id])
                            : '#';
                    @endphp
                    <div class="reveal relative" style="--reveal-delay:{{ ($i % 6) * 70 }}ms">
                        <div class="absolute top-3 end-3 z-20 flex flex-col gap-1.5">
                            <x-add-to-cart-button :model="$offer" type="offer" :clinic="$clinic" compact />
                            <x-save-button :model="$offer" type="offer" />
                            <x-compare-toggle type="offer" :id="$offer->id" :name="$offer->title" />
                        </div>
                        <a href="{{ $href }}"
                           class="flex flex-col h-full bg-white rounded-3xl shadow-soft hover:shadow-soft-lg hover:-translate-y-1 transition-all duration-300 overflow-hidden group">
                            <div class="relative aspect-[4/3] bg-gradient-to-br from-sage-mist to-gold-whisper flex items-center justify-center text-5xl">
                                @if($imageUrl)
                                    <img src="{{ $imageUrl }}"
                                         alt="" class="absolute inset-0 w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-500" loading="lazy">
                                @elseif($service?->categories?->first()?->emoji)
                                    <span aria-hidden="true">{{ $service->categories->first()->emoji }}</span>
                                @endif
                                {{-- Floating discount badge --}}
                                @if($discount && $discount > 0)
                                    <span class="absolute top-3 start-3 bg-red-500 text-white text-[11px] font-bold px-2.5 py-1 rounded-full shadow-soft-lg">
                                        {{ __('site.home_discount_off', ['pct' => $discount]) }}
                                    </span>
                                @endif
                                @if($offer->is_featured)
                                    <span class="absolute bottom-3 start-3 inline-flex items-center gap-1 bg-gradient-to-l from-gold-primary to-gold-deep text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-soft">
                                        <x-icon name="star-solid" class="w-2.5 h-2.5" /> @lang('site.featured')
                                    </span>
                                @endif
                            </div>
                            <div class="p-3.5 flex flex-col flex-1">
                                <div class="flex items-center justify-between gap-1.5">
                                    @if($clinic)
                                        <p class="text-[11px] text-slate-warm line-clamp-1">{{ $clinic->name }}</p>
                                    @endif
                                    @if($rating)
                                        <span class="shrink-0 inline-flex items-center gap-0.5 text-[11px] font-semibold text-charcoal">
                                            <x-icon name="star-solid" class="w-3 h-3 text-gold-primary" />{{ number_format((float) $rating, 1) }}
                                        </span>
                                    @endif
                                </div>
                                <h3 class="mt-0.5 font-display font-bold text-sm text-charcoal line-clamp-1 group-hover:text-sage-700 transition-colors">{{ $offer->title }}</h3>

                                <div class="mt-2 flex items-end gap-1.5 flex-wrap">
                                    @if($offer->price !== null)
                                        <span class="text-sage-deep font-bold text-lg leading-none">{{ number_format((float) $offer->price) }}<span class="text-[10px] font-normal ms-0.5"><x-riyal /></span></span>
                                    @endif
                                    @if($offer->old_price !== null)
                                        <span class="text-[11px] text-gray-400 line-through">{{ number_format((float) $offer->old_price) }}</span>
                                    @endif
                                </div>
                                @if($savings)
                                    <span class="mt-1.5 inline-flex w-fit items-center bg-gold-whisper text-gold-deep text-[10px] font-bold px-2 py-0.5 rounded-full">
                                        {{ __('site.home_offer_saved', ['amount' => number_format($savings)]) }}
                                    </span>
                                @endif

                                <span class="mt-3 inline-flex items-center justify-center gap-1.5 bg-sage-600 group-hover:bg-sage-700 text-white text-xs font-semibold py-2.5 rounded-xl shadow-soft transition-colors">
                                    <x-icon name="eye" class="w-3.5 h-3.5" /> @lang('site.home_view_offer')
                                </span>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
