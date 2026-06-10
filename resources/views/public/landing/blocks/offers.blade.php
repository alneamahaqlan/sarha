{{-- Offers block — running offers from the linked complex. --}}
@if($clinic)
    @php
        $limit = (int) ($cfg['item_limit'] ?? 6);
        $activeOffers = $clinic->offers
            ->filter(fn ($o) => $o->is_active && $o->starts_at <= now() && $o->ends_at >= now())
            ->take($limit)
            ->values();
    @endphp
    @if($activeOffers->isNotEmpty())
        <section class="max-w-5xl mx-auto px-4 py-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">{{ $cfg['heading'] ?? __('site.tab_offers') }}</h2>
            @include('public.partials.offers', ['clinic' => $clinic, 'activeOffers' => $activeOffers])
        </section>
    @endif
@endif
