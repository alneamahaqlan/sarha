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
            {{-- Unified compact offer-card (same as the clinic profile "عروض
                 إضافية") so offers look identical everywhere. --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                @foreach($offers as $i => $offer)
                    <div class="reveal" style="--reveal-delay:{{ ($i % 4) * 70 }}ms">
                        @include('public.partials.offer-card', ['offer' => $offer, 'clinic' => $offer->clinic, 'large' => false])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
