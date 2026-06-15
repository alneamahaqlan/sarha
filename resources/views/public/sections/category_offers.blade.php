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
        {{-- Unified compact offer-card — identical to the clinic profile
             "عروض إضافية" and the homepage latest-offers section. --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
            @foreach($offers as $i => $offer)
                <div class="reveal" style="--reveal-delay:{{ ($i % 4) * 70 }}ms">
                    @include('public.partials.offer-card', ['offer' => $offer, 'clinic' => $offer->clinic, 'large' => false])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
