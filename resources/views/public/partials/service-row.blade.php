{{--
    A single service CARD in the public clinic page (services tab). Compact
    vertical card: name + optional description on top, price + a prominent
    "احجز موعد" button at the bottom (every service exposes its own booking
    entry point — previously a faint text link customers routinely missed).
    Rendered inside a 2-up (mobile) / 3-up (desktop) grid.
    Expects: $service, $clinic
    Optional: $spec (bool) enables the hero specialty filter; $fallbackCats
    (array) — sub-clinic category ids used when the service has none tagged.
--}}
@php
    $specCats = [];
    if ($spec ?? false) {
        $specCats = $service->relationLoaded('categories') ? $service->categories->pluck('id')->all() : [];
        if (empty($specCats) && !empty($fallbackCats ?? [])) { $specCats = $fallbackCats; }
    }
@endphp
<div class="relative bg-white rounded-xl ring-1 ring-gray-100 hover:shadow-md hover:ring-sage-200 transition-all p-3 flex flex-col h-full"
     @if($spec ?? false) x-show="$store.spec.show(@js($specCats))" @endif>
    <div class="flex-1 min-w-0">
        <div class="flex items-start gap-1.5">
            <a href="{{ route('service.show', ['slug' => $clinic->slug, 'service' => $service->id]) }}"
               class="text-sm font-semibold text-gray-800 hover:text-sage-700 transition-colors line-clamp-2">{{ $service->name }}</a>
            <div class="flex items-center gap-1 ms-auto flex-shrink-0">
                <x-add-to-cart-button :model="$service" type="service" :clinic="$clinic" compact />
                <x-compare-toggle type="service" :id="$service->id" :name="$service->name" />
                <x-save-button :model="$service" type="service" />
            </div>
        </div>
        @if($service->description)
            <p class="text-[11px] text-gray-500 mt-1 line-clamp-2">{{ $service->description }}</p>
        @endif
    </div>

    @php
        // Live discounted price (the service's inline offer), when running.
        $offer = $service->relationLoaded('inlineOffer') && $service->inlineOffer && $service->inlineOffer->isRunning()
            ? $service->inlineOffer
            : null;
    @endphp
    <div class="mt-3">
        @if($service->price)
            <div class="mb-2 flex items-baseline gap-2 flex-wrap">
                <span class="text-sage-700 font-bold whitespace-nowrap">
                    @if($service->price_from)
                        <span class="text-[10px] font-normal text-gray-500">@lang('site.price_from')</span>
                    @endif
                    {{ number_format($offer ? (float) $offer->price : (float) $service->price) }}
                    <span class="text-[10px] font-normal"><x-riyal /></span>
                </span>
                @if($offer)
                    <span class="text-gray-400 text-xs line-through whitespace-nowrap">{{ number_format((float) $service->price) }}</span>
                    @if($offer->discountPercentage())
                        <span class="text-[10px] font-bold text-rose-600 bg-rose-50 rounded px-1.5 py-0.5">-{{ $offer->discountPercentage() }}%</span>
                    @endif
                @endif
            </div>
        @else
            <div class="mb-2">
                <span class="text-gray-400 text-xs">@lang('site.call_for_inquiry')</span>
            </div>
        @endif
        <a href="{{ route('clinic.book.form', ['slug' => $clinic->slug, 'service' => $service->id]) }}"
           data-track="booking" data-clinic="{{ $clinic->id }}"
           class="w-full inline-flex items-center justify-center gap-1.5 bg-sage-600 hover:bg-sage-700 text-white text-xs font-semibold px-2 py-2 rounded-lg shadow-sm transition-colors whitespace-nowrap">
            <x-icon name="calendar" class="w-3.5 h-3.5" />
            @lang('site.book_appointment')
        </a>
    </div>
</div>
