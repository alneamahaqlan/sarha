{{--
    One offer card — renders both featured (large variant) and regular
    (compact). The visitor sees the same information either way; size
    differs because featured slots want to "earn the click".

    Expects: $offer (App\Models\Offer), $clinic, $large (bool)
--}}
@php
    $imageUrl  = $offer->effectiveImage()
        ? \Illuminate\Support\Facades\Storage::url($offer->effectiveImage())
        : null;
    $discount  = $offer->discountPercentage();
    // Countdown payload — rendered by Alpine on the client so it ticks
    // without a refresh. JS gets the ISO timestamp directly.
    $endsAtIso = $offer->ends_at->toIso8601String();
    // CTA is type-conditional: service-linked → deep-link to the booking
    // form for that specific service; general → call/whatsapp the clinic.
    $isServiceLinked = $offer->type === \App\Models\Offer::TYPE_SERVICE && $offer->service;
    $ctaHref = $isServiceLinked
        ? route('clinic.book.form', ['slug' => $clinic->slug, 'service' => $offer->service_id])
        : ($clinic->whatsappLink() ?: ($clinic->phone ? 'tel:' . $clinic->phone : '#'));
    $ctaLabel = $isServiceLinked
        ? __('site.book_this_service')
        : __('site.contact_for_inquiry');
@endphp

<div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 hover:shadow-lg transition-all overflow-hidden flex flex-col">
    <div class="relative {{ $large ? 'aspect-[16/9]' : 'aspect-[4/3]' }} bg-gradient-to-br from-sage-mist to-gold-whisper flex items-center justify-center text-4xl">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $offer->title }}" loading="lazy"
                 class="absolute inset-0 w-full h-full object-cover">
        @endif
        @if($discount)
            <span class="absolute top-3 start-3 bg-red-500 text-white text-xs font-bold px-2.5 py-1 rounded-full shadow-md">
                -{{ $discount }}%
            </span>
        @endif
        @if($offer->is_featured)
            <span class="absolute top-3 end-3 inline-flex items-center gap-1 bg-gold-deep text-white text-[11px] font-bold px-2 py-1 rounded-full shadow-md">
                <x-icon name="star-solid" class="w-3 h-3" /> @lang('site.featured')
            </span>
        @endif
    </div>

    <div class="p-4 flex-1 flex flex-col">
        <h3 class="{{ $large ? 'text-base' : 'text-sm' }} font-bold text-gray-800 line-clamp-2">{{ $offer->title }}</h3>

        @if($isServiceLinked)
            <p class="text-xs text-gray-500 mt-1 line-clamp-1">
                @lang('site.offer_on_service'): {{ $offer->service->name }}
            </p>
        @else
            <p class="text-xs text-gold-deep mt-1 font-semibold">
                @lang('site.offer_type_general')
            </p>
        @endif

        @if($offer->description)
            <p class="text-sm text-gray-500 mt-2 line-clamp-2">{{ $offer->description }}</p>
        @endif

        @if($offer->price !== null)
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-sage-700 font-bold {{ $large ? 'text-xl' : 'text-base' }}">
                    {{ number_format((float) $offer->price) }}
                    <span class="text-xs font-normal">@lang('site.currency_sar')</span>
                </span>
                @if($offer->old_price !== null)
                    <span class="text-sm text-gray-400 line-through">{{ number_format((float) $offer->old_price) }}</span>
                @endif
            </div>
        @endif

        {{-- Countdown — Alpine ticks every second. Shows "ينتهي خلال
             N أيام / H ساعات" or a red warning under 24h. --}}
        <div class="mt-3 text-xs"
             x-data="offerCountdown('{{ $endsAtIso }}')"
             x-init="start()">
            <span :class="urgent ? 'text-red-600 font-semibold' : 'text-amber-600'">
                <x-icon name="clock" class="w-3.5 h-3.5 inline-block" />
                <span x-text="label"></span>
            </span>
        </div>

        <a href="{{ $ctaHref }}"
           @if(! $isServiceLinked) target="_blank" rel="noopener" @endif
           data-track="{{ $isServiceLinked ? 'booking' : 'contact' }}" data-clinic="{{ $clinic->id }}"
           class="mt-4 inline-flex items-center justify-center gap-2 min-h-touch bg-sage-600 hover:bg-sage-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg shadow-sm transition-colors">
            <x-icon name="{{ $isServiceLinked ? 'calendar' : 'phone' }}" class="w-4 h-4" />
            {{ $ctaLabel }}
        </a>
    </div>
</div>
