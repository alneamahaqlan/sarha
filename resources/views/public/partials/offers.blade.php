{{--
    Offers tab — standalone Offer entities (replaced the legacy
    is_featured_offer flag on services). Two visual tiers:
      • Featured offers (1–3) get a wider card at the top
      • Remaining active offers fill a 2-column grid below
    Each card carries its discount badge, prices, a countdown, and a
    type-appropriate CTA: "احجز هذه الخدمة" for service-linked offers,
    "تواصل للاستفسار" for general promos.
    Expects: $clinic, $activeOffers (Collection of Offer)
--}}
@php
    $activeOffers = $activeOffers ?? collect();
    $featured = $activeOffers->where('is_featured', true)->values();
    $regular  = $activeOffers->where('is_featured', false)->values();
@endphp

@if($activeOffers->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        @lang('site.no_offers_yet')
    </div>
@else
    @if($featured->isNotEmpty())
        <div class="space-y-4">
            <h2 class="text-lg font-bold text-gray-800">@lang('site.featured_offers_title')</h2>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                @foreach($featured as $offer)
                    @include('public.partials.offer-card', ['offer' => $offer, 'clinic' => $clinic, 'large' => false, 'spec' => $spec ?? false])
                @endforeach
            </div>
        </div>
    @endif

    @if($regular->isNotEmpty())
        <div class="space-y-4">
            @if($featured->isNotEmpty())
                <h2 class="text-lg font-bold text-gray-800 mt-6">@lang('site.more_offers_title')</h2>
            @endif
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                @foreach($regular as $offer)
                    @include('public.partials.offer-card', ['offer' => $offer, 'clinic' => $clinic, 'large' => false, 'spec' => $spec ?? false])
                @endforeach
            </div>
        </div>
    @endif
@endif
